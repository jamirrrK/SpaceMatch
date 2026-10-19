<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestStatusHistory;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        $activeBookings = $request->user()->role === 'renter'
            ? Booking::visibleTo($request->user())->where('status', 'active')->with('space.property')->get()
            : collect();

        return view('maintenance.index', compact('requests', 'activeBookings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);
        $path = null;
        try {
            $maintenance = DB::transaction(function () use ($request, $data, &$path): MaintenanceRequest {
                $booking = Booking::where('renter_id', $request->user()->id)->whereKey($data['booking_id'])
                    ->lockForUpdate()->firstOrFail();
                if ($booking->status !== 'active') {
                    throw ValidationException::withMessages(['booking_id' => 'Maintenance can only be reported for an active rental.']);
                }
                $existing = MaintenanceRequest::where('booking_id', $booking->id)
                    ->where('description', $data['description'])->whereIn('status', ['open', 'in_progress'])->first();
                if ($existing) {
                    return $existing;
                }
                if ($request->hasFile('evidence')) {
                    $path = $request->file('evidence')->store('maintenance-evidence', 'local');
                    if (! $path) {
                        throw new \RuntimeException('Unable to save private evidence.');
                    }
                }
                $maintenance = MaintenanceRequest::create([
                    'booking_id' => $booking->id, 'description' => $data['description'],
                    'status' => 'open', 'evidence_path' => $path,
                ]);
                $maintenance->statusHistory()->create([
                    'actor_id' => $request->user()->id, 'from_status' => 'submitted', 'to_status' => 'open', 'notes' => 'Issue reported by the renter.',
                ]);
                $booking->space->property->owner->notify(new RentalWorkflowNotification(
                    'New maintenance request', 'A renter reported an issue for '.$booking->reference.'.', route('maintenance.index')
                ));

                return $maintenance;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('maintenance.index')->with('status', 'Maintenance request recorded.');
    }

    public function evidence(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        abort_unless(Booking::visibleTo($request->user())->whereKey($maintenanceRequest->booking_id)->exists(), 404);
        abort_unless($maintenanceRequest->evidence_path && Storage::disk('local')->exists($maintenanceRequest->evidence_path), 404);

        return Storage::disk('local')->download($maintenanceRequest->evidence_path, 'maintenance-evidence.'.pathinfo($maintenanceRequest->evidence_path, PATHINFO_EXTENSION), [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'resolved', 'closed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_response' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($data['status'] === 'resolved' && blank($data['notes'] ?? $data['owner_response'] ?? null)) {
            throw ValidationException::withMessages(['notes' => 'Add a short note describing how the issue was resolved.']);
        }

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
            $changes = ['status' => $data['status']];
            if (Schema::hasColumn('maintenance_requests', 'owner_response')) {
                $changes['owner_response'] = $data['owner_response'] ?? $data['notes'] ?? $ownedRequest->owner_response;
            }
            if (Schema::hasColumn('maintenance_requests', 'resolved_at') && $data['status'] === 'resolved') {
                $changes['resolved_at'] = now();
            }
            $ownedRequest->update($changes);
            MaintenanceRequestStatusHistory::query()->create([
                'maintenance_request_id' => $ownedRequest->id,
                'actor_id' => $request->user()->id,
                'from_status' => $previousStatus,
                'to_status' => $data['status'],
                'notes' => $data['notes'] ?? $data['owner_response'] ?? null,
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
