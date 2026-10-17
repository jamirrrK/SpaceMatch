<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\RentalRequest;
use App\Models\RentalRequestHistory;
use App\Models\Property;
use App\Models\Space;
use App\Notifications\RentalWorkflowNotification;
use App\Services\RentalPaymentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RentalRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('owner_id', $request->user()->id)],
            'status' => ['nullable', Rule::in([
                RentalRequest::STATUS_PENDING,
                RentalRequest::STATUS_APPROVED,
                RentalRequest::STATUS_REJECTED,
                RentalRequest::STATUS_CANCELLED,
                RentalRequest::STATUS_MORE_INFORMATION,
            ])],
        ]);
        $requests = RentalRequest::visibleTo($request->user())
            ->when($filters['property_id'] ?? null, fn ($query, $id) => $query->whereHas('space', fn ($space) => $space->where('property_id', $id)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['renter', 'space.property', 'booking', 'statusHistory.actor'])
            ->latest()
            ->paginate(15)->withQueryString();
        $properties = Property::query()->where('owner_id', $request->user()->id)->orderBy('property_name')->get(['id', 'property_name']);

        return view('owner.requests.index', compact('requests', 'filters', 'properties'));
    }

    public function update(Request $request, RentalRequest $rentalRequest, RentalPaymentSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                RentalRequest::STATUS_APPROVED,
                RentalRequest::STATUS_REJECTED,
                RentalRequest::STATUS_MORE_INFORMATION,
            ])],
            'response_message' => ['required_if:status,'.RentalRequest::STATUS_REJECTED, 'required_if:status,'.RentalRequest::STATUS_MORE_INFORMATION, 'nullable', 'string', 'max:2000'],
        ]);

        $ownedRequest = DB::transaction(function () use ($request, $rentalRequest, $data, $schedule): RentalRequest {
            $ownedRequest = RentalRequest::query()
                ->whereKey($rentalRequest->id)
                ->whereHas('space.property', fn ($property) => $property->where('owner_id', $request->user()->id))
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ownedRequest->status, [
                RentalRequest::STATUS_PENDING,
                RentalRequest::STATUS_MORE_INFORMATION,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'This request can no longer be changed.']);
            }

            $previousStatus = $ownedRequest->status;
            if ($data['status'] === RentalRequest::STATUS_APPROVED) {
                $space = Space::query()->whereKey($ownedRequest->space_id)->lockForUpdate()->firstOrFail();
                if (! Space::publiclyListed()->whereKey($space->id)->exists()) {
                    throw ValidationException::withMessages(['status' => 'This space is no longer published and available.']);
                }

                $startDate = $ownedRequest->move_in_date->copy();
                $endDate = $startDate->copy()->addMonthsNoOverflow($ownedRequest->duration_months)->subDay();
                if ($space->available_from && $startDate->lt($space->available_from)) {
                    throw ValidationException::withMessages(['status' => 'This space is not available by the requested move-in date.']);
                }
                $occupied = Booking::query()
                    ->where('space_id', $space->id)
                    ->whereIn('status', ['confirmed', 'active'])
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->sum('occupants');

                if ($occupied + $ownedRequest->occupants > $space->capacity) {
                    throw ValidationException::withMessages(['status' => 'There is not enough capacity for these dates.']);
                }

                $booking = Booking::query()->create([
                    'renter_id' => $ownedRequest->renter_id,
                    'space_id' => $space->id,
                    'reference' => 'SM-'.Str::upper((string) Str::ulid()),
                    'occupants' => $ownedRequest->occupants,
                    'monthly_rent' => $space->price,
                    'deposit_amount' => $space->deposit_amount,
                    'advance_months' => $space->advance_months,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'confirmed',
                ]);
                BookingStatusHistory::query()->create([
                    'booking_id' => $booking->id,
                    'actor_id' => $request->user()->id,
                    'from_status' => null,
                    'to_status' => 'confirmed',
                    'notes' => 'Created from an owner-approved rental request.',
                ]);
                $schedule->ensureForBooking($booking);
                $ownedRequest->booking()->associate($booking);
            }

            $ownedRequest->fill([
                'status' => $data['status'],
                'response_message' => $data['response_message'] ?? null,
                'responded_at' => now(),
            ])->save();
            RentalRequestHistory::query()->create([
                'rental_request_id' => $ownedRequest->id,
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role,
                'event' => 'owner_decision',
                'from_status' => $previousStatus,
                'to_status' => $ownedRequest->status,
                'notes' => $data['response_message'] ?? null,
            ]);

            return $ownedRequest->load('renter', 'statusHistory.actor');
        });

        $ownedRequest->renter->notify(new RentalWorkflowNotification(
            match ($ownedRequest->status) {
                RentalRequest::STATUS_APPROVED => 'Rental request approved',
                RentalRequest::STATUS_REJECTED => 'Rental request declined',
                default => 'More information requested',
            },
            $ownedRequest->response_message ?: match ($ownedRequest->status) {
                RentalRequest::STATUS_APPROVED => 'The owner approved your request and confirmed a booking.',
                RentalRequest::STATUS_REJECTED => 'The owner declined your rental request.',
                default => 'The owner needs more information about your request.',
            },
            route($ownedRequest->status === RentalRequest::STATUS_APPROVED ? 'bookings.index' : 'renter.requests.index')
        ));

        return back()->with('status', 'Rental request response saved.');
    }
}
