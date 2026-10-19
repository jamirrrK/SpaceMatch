<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingInformationRequest;
use App\Models\BookingStatusHistory;
use App\Models\Inquiry;
use App\Models\Space;
use App\Notifications\RentalWorkflowNotification;
use App\Services\RentalWorkflowSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        app(RentalWorkflowSchema::class)->ensureAvailable();
        $renter = $request->user();
        abort_unless($renter->hasRole('renter'), 403);

        if ($renter->is_student) {
            throw ValidationException::withMessages([
                'booking' => 'Student renters must submit requests through the student rental terms workflow.',
            ]);
        }

        $data = $request->validate([
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'occupants' => ['required', 'integer', 'min:1'],
            'renter_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = DB::transaction(function () use ($renter, $data): Booking {
            $space = Space::publiclyListed()->whereKey($data['space_id'])->lockForUpdate()->firstOrFail();

            if ($space->available_from && $space->available_from->gt($data['start_date'])) {
                throw ValidationException::withMessages(['start_date' => 'This space is not available by the requested start date.']);
            }

            if ((int) $data['occupants'] > (int) $space->capacity) {
                throw ValidationException::withMessages(['occupants' => "The number of occupants exceeds this space's capacity."]);
            }

            $overlap = Booking::query()
                ->where('space_id', $space->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'Those dates overlap a confirmed rental.']);
            }

            $booking = Booking::query()->create([
                'renter_id' => $renter->id,
                'space_id' => $space->id,
                'reference' => 'SM-'.Str::upper((string) Str::ulid()),
                'occupants' => $data['occupants'],
                'monthly_rent' => $space->price,
                'agreed_amount' => number_format((float) $space->price * (int) $data['occupants'], 2, '.', ''),
                'renter_message' => $data['renter_message'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'pending',
            ]);

            BookingStatusHistory::query()->create([
                'booking_id' => $booking->id,
                'actor_id' => $renter->id,
                'from_status' => null,
                'to_status' => 'pending',
                'notes' => 'Rental request submitted.',
            ]);

            return $booking;
        });

        $booking->space->property->owner->notify(new RentalWorkflowNotification(
            'New rental request',
            'A renter submitted a rental request for '.$booking->space->space_name.'.',
            route('owner.rentals.index'),
        ));

        return redirect()->route('bookings.index')->with('status', 'Your rental request was sent to the property owner.');
    }

    public function respondToInformationRequest(Request $request, BookingInformationRequest $informationRequest): RedirectResponse
    {
        app(RentalWorkflowSchema::class)->ensureAvailable();
        $data = $request->validate(['response' => ['required', 'string', 'max:2000']]);

        $informationRequest = DB::transaction(function () use ($request, $informationRequest, $data): BookingInformationRequest {
            $booking = Booking::query()
                ->whereKey($informationRequest->booking_id)
                ->where('renter_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status !== 'awaiting_renter_information') {
                throw ValidationException::withMessages(['response' => 'This booking is not waiting for additional information.']);
            }

            $lockedRequest = BookingInformationRequest::query()
                ->whereKey($informationRequest->id)
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->responded_at !== null) {
                throw ValidationException::withMessages(['response' => 'This information request has already been answered.']);
            }

            $lockedRequest->update(['response' => $data['response'], 'responded_at' => now()]);
            $booking->update(['status' => 'pending', 'decision_reason' => null, 'decided_at' => null]);
            BookingStatusHistory::query()->create([
                'booking_id' => $booking->id,
                'actor_id' => $request->user()->id,
                'from_status' => 'awaiting_renter_information',
                'to_status' => 'pending',
                'notes' => 'Renter provided the requested information.',
            ]);

            return $lockedRequest->load('booking.space.property.owner');
        });

        $informationRequest->booking->space->property->owner->notify(new RentalWorkflowNotification(
            'Renter provided additional information',
            'A renter responded to your question about '.$informationRequest->booking->space->space_name.'.',
            route('owner.rentals.index'),
        ));

        return back()->with('status', 'Your response was sent to the property owner.');
    }

    public function show(Request $request, Booking $booking): View
    {
        $booking = Booking::visibleTo($request->user())->whereKey($booking->id)
            ->with(['space.property.owner', 'renter', 'rentalRequest', 'payments.transactions', 'statusHistory.actor'])
            ->firstOrFail();
        $inquiry = Inquiry::where('space_id', $booking->space_id)
            ->where('renter_id', $booking->renter_id)->where('owner_id', $booking->space->property->owner_id)
            ->where('status', 'open')->latest()->first();

        return view('bookings.show', compact('booking', 'inquiry'));
    }

    public function index(Request $request): View
    {
        $bookings = Booking::visibleTo($request->user())
            ->with(['space.property', 'renter', 'statusHistory', 'reviews'])
            ->latest()->paginate(15);

        return view('bookings.index', compact('bookings'));
    }
}
