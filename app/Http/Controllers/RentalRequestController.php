<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\RentalRequest;
use App\Models\RentalRequestHistory;
use App\Models\Space;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RentalRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = $request->user()->rentalRequests()
            ->with(['space.property', 'booking', 'statusHistory.actor'])
            ->latest()
            ->paginate(15);

        return view('renter.requests.index', compact('requests'));
    }

    public function store(Request $request, Space $space): RedirectResponse
    {
        $data = $request->validate([
            'move_in_date' => ['required', 'date', 'after_or_equal:today'],
            'occupants' => ['required', 'integer', 'min:1', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:36'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $rentalRequest = DB::transaction(function () use ($request, $space, $data): RentalRequest {
            $space = Space::query()->whereKey($space->id)->lockForUpdate()->with('property')->firstOrFail();
            if ($space->property->owner_id === $request->user()->id) {
                throw ValidationException::withMessages(['space' => 'You cannot request to rent your own listing.']);
            }
            if (! Space::publiclyListed()->whereKey($space->id)->exists()) {
                throw ValidationException::withMessages(['space' => 'This rental space is no longer available for requests.']);
            }
            if ($data['occupants'] > $space->capacity) {
                throw ValidationException::withMessages(['occupants' => 'The number of occupants exceeds this space’s capacity.']);
            }
            $startDate = Carbon::parse($data['move_in_date']);
            if ($space->available_from && $startDate->lt($space->available_from)) {
                throw ValidationException::withMessages(['move_in_date' => 'This space is not available by your selected move-in date.']);
            }

            $endDate = $startDate->copy()->addMonthsNoOverflow((int) $data['duration_months'])->subDay();
            $occupied = Booking::query()
                ->where('space_id', $space->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->sum('occupants');
            if ($occupied + $data['occupants'] > $space->capacity) {
                throw ValidationException::withMessages(['move_in_date' => 'There is not enough capacity for this space on your selected dates.']);
            }

            $alreadyRequested = $request->user()->rentalRequests()
                ->where('space_id', $space->id)
                ->where(function ($query): void {
                    $query->whereIn('status', [
                        RentalRequest::STATUS_PENDING,
                        RentalRequest::STATUS_MORE_INFORMATION,
                    ])->orWhere(function ($approved): void {
                        $approved->where('status', RentalRequest::STATUS_APPROVED)
                            ->whereHas('booking', fn ($booking) => $booking->whereIn('status', ['confirmed', 'active']));
                    });
                })->exists();

            if ($alreadyRequested) {
                throw ValidationException::withMessages(['space' => 'You already have an active request for this space.']);
            }

            $rentalRequest = $request->user()->rentalRequests()->create($data + ['space_id' => $space->id]);
            RentalRequestHistory::query()->create([
                'rental_request_id' => $rentalRequest->id,
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role,
                'event' => 'submitted',
                'to_status' => $rentalRequest->status,
                'notes' => $rentalRequest->message,
            ]);

            return $rentalRequest->load('space.property.owner');
        });

        $rentalRequest->space->property->owner->notify(new RentalWorkflowNotification(
            'New rental request',
            'A renter sent a request for '.$rentalRequest->space->space_name.'.',
            route('owner.requests.index')
        ));

        return redirect()->route('renter.requests.index')->with('status', 'Your rental request was sent to the owner.');
    }

    public function respond(Request $request, RentalRequest $rentalRequest): RedirectResponse
    {
        $data = $request->validate(['renter_response' => ['required', 'string', 'max:2000']]);

        $ownedRequest = DB::transaction(function () use ($request, $rentalRequest, $data): RentalRequest {
            $ownedRequest = $request->user()->rentalRequests()->whereKey($rentalRequest->id)->lockForUpdate()->firstOrFail();
            if ($ownedRequest->status !== RentalRequest::STATUS_MORE_INFORMATION) {
                throw ValidationException::withMessages(['renter_response' => 'This request is not waiting for additional information.']);
            }

            $previousStatus = $ownedRequest->status;
            $ownedRequest->update([
                'renter_response' => $data['renter_response'],
                'status' => RentalRequest::STATUS_PENDING,
            ]);
            RentalRequestHistory::query()->create([
                'rental_request_id' => $ownedRequest->id,
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role,
                'event' => 'renter_reply',
                'from_status' => $previousStatus,
                'to_status' => $ownedRequest->status,
                'notes' => $data['renter_response'],
            ]);

            return $ownedRequest->load('space.property.owner');
        });

        $ownedRequest->space->property->owner->notify(new RentalWorkflowNotification(
            'Renter sent more information',
            'A renter responded to your request for '.$ownedRequest->space->space_name.'.',
            route('owner.requests.index')
        ));

        return back()->with('status', 'Your response was sent to the owner.');
    }

    public function cancel(Request $request, RentalRequest $rentalRequest): RedirectResponse
    {
        $ownedRequest = DB::transaction(function () use ($request, $rentalRequest): RentalRequest {
            $ownedRequest = $request->user()->rentalRequests()->whereKey($rentalRequest->id)->lockForUpdate()->firstOrFail();
            if (! in_array($ownedRequest->status, [
                RentalRequest::STATUS_PENDING,
                RentalRequest::STATUS_MORE_INFORMATION,
            ], true)) {
                throw ValidationException::withMessages(['request' => 'Only requests awaiting an owner decision can be cancelled.']);
            }

            $previousStatus = $ownedRequest->status;
            $ownedRequest->update(['status' => RentalRequest::STATUS_CANCELLED]);
            RentalRequestHistory::query()->create([
                'rental_request_id' => $ownedRequest->id,
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role,
                'event' => 'renter_cancelled',
                'from_status' => $previousStatus,
                'to_status' => $ownedRequest->status,
            ]);

            return $ownedRequest->load('space.property.owner');
        });

        $ownedRequest->space->property->owner->notify(new RentalWorkflowNotification(
            'Rental request cancelled',
            'A renter cancelled a request for '.$ownedRequest->space->space_name.'.',
            route('owner.requests.index')
        ));

        return back()->with('status', 'Your rental request was cancelled.');
    }
}
