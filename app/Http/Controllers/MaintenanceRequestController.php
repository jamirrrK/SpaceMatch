<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestStatusHistory;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = MaintenanceRequest::with(['booking.space.property', 'booking.renter', 'statusHistory.actor'])
            ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->latest()->paginate(15);

        return view('maintenance.index', compact('requests'));
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'resolved', 'closed'])],
            'notes' => ['nullable', 'required_if:status,resolved', 'string', 'max:2000'],
        ]);

        $maintenanceRequest = DB::transaction(function () use ($request, $maintenanceRequest, $data): MaintenanceRequest {
            $ownedRequest = MaintenanceRequest::query()
                ->whereKey($maintenanceRequest->id)
                ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
                ->lockForUpdate()
                ->firstOrFail();
            $nextStatuses = match ($ownedRequest->status) {
                'open' => ['in_progress'],
                'in_progress' => ['resolved'],
                'resolved' => ['closed'],
                default => [],
            };

            if (! in_array($data['status'], $nextStatuses, true)) {
                throw ValidationException::withMessages(['status' => 'This maintenance request cannot make that status transition.']);
            }

            $previousStatus = $ownedRequest->status;
            $ownedRequest->update(['status' => $data['status']]);
            MaintenanceRequestStatusHistory::query()->create([
                'maintenance_request_id' => $ownedRequest->id,
                'actor_id' => $request->user()->id,
                'from_status' => $previousStatus,
                'to_status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $ownedRequest->load('booking.renter', 'statusHistory');
        });

        $maintenanceRequest->booking->renter->notify(new RentalWorkflowNotification(
            'Maintenance request updated',
            'Your reported maintenance issue is now '.$maintenanceRequest->status.'.',
            route('maintenance.index')
        ));

        return back()->with('status', 'Maintenance request status updated.');
    }
}
