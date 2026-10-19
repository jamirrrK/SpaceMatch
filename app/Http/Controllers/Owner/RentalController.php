<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingInformationRequest;
use App\Models\BookingStatusHistory;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Space;
use App\Notifications\RentalWorkflowNotification;
use App\Services\RentalWorkflowSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RentalController extends Controller
{
    private function ownedBookings(Request $request)
    {
        return Booking::whereHas('space.property', fn ($q) => $q->where('owner_id', $request->user()->id));
    }

    public function decide(Request $request, Booking $booking)
    {
        app(RentalWorkflowSchema::class)->ensureAvailable();
        $data = $request->validate([
            'decision' => ['required', Rule::in(['reject', 'more_details', 'approve'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['decision'] === 'more_details' && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Enter the information you need from the renter.']);
        }

        [$booking, $message] = DB::transaction(function () use ($request, $booking, $data): array {
            $lockedBooking = $this->ownedBookings($request)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Only pending rental requests can receive a decision.']);
            }

            if ($data['decision'] === 'more_details') {
                BookingInformationRequest::create([
                    'booking_id' => $lockedBooking->id,
                    'owner_id' => $request->user()->id,
                    'question' => $data['reason'],
                ]);
                $lockedBooking->update(['status' => 'awaiting_renter_information', 'decided_at' => now()]);
                $historyNotes = $data['reason'];
                $message = 'The owner requested additional information for your rental request.';
            } elseif ($data['decision'] === 'reject') {
                $lockedBooking->update([
                    'status' => 'rejected',
                    'decision_reason' => $data['reason'] ?? null,
                    'decided_at' => now(),
                ]);
                $historyNotes = $data['reason'] ?? null;
                $message = 'Your rental request was declined.'.(filled($data['reason'] ?? null) ? ' Reason: '.$data['reason'] : '');
            } else {
                $space = Space::query()->whereKey($lockedBooking->space_id)->lockForUpdate()->firstOrFail();
                if (! Space::publiclyListed()->whereKey($space->id)->exists()) {
                    throw ValidationException::withMessages(['decision' => 'This space is no longer published and available.']);
                }

                if ($space->available_from && $lockedBooking->start_date->lt($space->available_from)) {
                    throw ValidationException::withMessages(['decision' => 'This space is not available by the requested start date.']);
                }

                $overlap = Booking::query()
                    ->where('space_id', $space->id)
                    ->whereIn('status', ['confirmed', 'active'])
                    ->whereDate('start_date', '<=', $lockedBooking->end_date)
                    ->whereDate('end_date', '>=', $lockedBooking->start_date)
                    ->lockForUpdate()
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages(['decision' => 'Those dates overlap a confirmed rental.']);
                }

                $lockedBooking->update([
                    'status' => 'confirmed',
                    'decided_at' => now(),
                    'confirmed_at' => now(),
                ]);
                $historyNotes = 'Owner approved the rental request.';
                Payment::firstOrCreate(['booking_id' => $lockedBooking->id], [
                    'amount' => $lockedBooking->agreed_amount ?? $space->price,
                    'payment_status' => 'pending',
                ]);
                $message = 'Your rental request was approved and the booking is confirmed.';
            }

            BookingStatusHistory::query()->create([
                'booking_id' => $lockedBooking->id,
                'actor_id' => $request->user()->id,
                'from_status' => 'pending',
                'to_status' => $lockedBooking->status,
                'notes' => $historyNotes,
            ]);

            return [$lockedBooking->load('renter'), $message];
        });

        $booking->renter->notify(new RentalWorkflowNotification(
            'Rental request updated',
            $message,
            route('bookings.index'),
        ));

        return back()->with('status', 'Rental request updated and the renter has been notified.');
    }

    public function index(Request $request)
    {
        app(RentalWorkflowSchema::class)->ensureAvailable();
        $bookings = $this->ownedBookings($request)->with(['renter', 'space.property', 'informationRequests'])
            ->latest()->paginate(15);

        return view('owner.rentals.index', compact('bookings'));
    }

    public function complete(Request $request, Booking $booking)
    {
        app(RentalWorkflowSchema::class)->ensureAvailable();
        $booking = DB::transaction(function () use ($request, $booking): Booking {
            $ownedBooking = $this->ownedBookings($request)->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! in_array($ownedBooking->status, ['confirmed', 'active'], true) || $ownedBooking->end_date->copy()->endOfDay()->isFuture()) {
                throw ValidationException::withMessages(['rental' => 'A rental can be completed only after its end date.']);
            }
            $previousStatus = $ownedBooking->status;
            $ownedBooking->update(['status' => 'completed', 'completed_at' => now()]);
            BookingStatusHistory::query()->create([
                'booking_id' => $ownedBooking->id,
                'actor_id' => $request->user()->id,
                'from_status' => $previousStatus,
                'to_status' => 'completed',
                'notes' => 'Rental completed by the property owner.',
            ]);

            return $ownedBooking->load('renter');
        });

        $booking->renter->notify(new RentalWorkflowNotification(
            'Rental completed',
            'Your rental has been marked complete.',
            route('bookings.index'),
        ));

        return back()->with('status', 'Rental completed. Its history and payment records have been kept.');
    }

    public function activate(Request $request, Booking $booking)
    {
        app(RentalWorkflowSchema::class)->ensureAvailable();
        DB::transaction(function () use ($request, $booking): void {
            $ownedBooking = $this->ownedBookings($request)->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $today = now()->toDateString();
            if ($ownedBooking->status !== 'confirmed' || $ownedBooking->start_date->toDateString() > $today || $ownedBooking->end_date->toDateString() < $today) {
                throw ValidationException::withMessages(['rental' => 'A rental can be activated only during its confirmed rental period.']);
            }
            $ownedBooking->update(['status' => 'active']);
            BookingStatusHistory::query()->create([
                'booking_id' => $ownedBooking->id,
                'actor_id' => $request->user()->id,
                'from_status' => 'confirmed',
                'to_status' => 'active',
                'notes' => 'Rental activated by the property owner.',
            ]);
        });

        return back()->with('status', 'Rental is now active and available in your active rentals list.');
    }

    public function recordPayment(Request $request, Payment $payment)
    {
        return app(PaymentController::class)->update($request, $payment);
    }

    public function updateMaintenance(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        abort_unless($request->user()->hasRole('owner'), 403);

        return app(\App\Http\Controllers\MaintenanceRequestController::class)->update($request, $maintenanceRequest);
    }

    public function reports(Request $request)
    {
        $owner = $request->user();
        $filters = $request->validate([
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('owner_id', $owner->id)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['pending', 'awaiting_renter_information', 'confirmed', 'active', 'completed', 'rejected'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'partially_paid', 'paid'])],
        ]);
        $bookings = $this->ownedBookings($request)
            ->when($filters['property_id'] ?? null, fn ($q, $id) => $q->whereHas('space', fn ($space) => $space->where('property_id', $id)))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $payments = Payment::whereHas('booking.space.property', fn ($q) => $q->where('owner_id', $owner->id))
            ->when($filters['property_id'] ?? null, fn ($q, $id) => $q->whereHas('booking.space', fn ($space) => $space->where('property_id', $id)))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '<=', $date))
            ->when($filters['payment_status'] ?? null, fn ($q, $status) => $q->where('payment_status', $status));
        $stats = [
            'properties' => $owner->properties()->count(),
            'published' => $owner->properties()->where('listing_status', 'published')->count(),
            'requests' => (clone $bookings)->count(),
            'approved' => (clone $bookings)->whereIn('status', ['confirmed', 'active', 'completed'])->count(),
            'rejected' => (clone $bookings)->where('status', 'rejected')->count(),
            'active' => (clone $bookings)->where('status', 'active')->count(),
            'completed' => (clone $bookings)->where('status', 'completed')->count(),
            'maintenance' => MaintenanceRequest::whereHas('booking.space.property', fn ($q) => $q->where('owner_id', $owner->id))->count(),
            'income' => (clone $payments)->sum('paid_amount'),
            'outstanding' => (clone $payments)->whereRaw('amount > paid_amount')->sum(DB::raw('amount - paid_amount')),
        ];
        $incomeByProperty = Payment::selectRaw('properties.id as property_id, properties.property_name, SUM(payments.paid_amount) as paid_income')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')->join('spaces', 'spaces.id', '=', 'bookings.space_id')
            ->join('properties', 'properties.id', '=', 'spaces.property_id')->where('properties.owner_id', $owner->id)
            ->when($filters['property_id'] ?? null, fn ($q, $id) => $q->where('properties.id', $id))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('payments.payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('payments.payment_date', '<=', $date))
            ->when($filters['payment_status'] ?? null, fn ($q, $status) => $q->where('payments.payment_status', $status))
            ->groupBy('properties.id', 'properties.property_name')->orderBy('properties.property_name')->get();
        $monthExpression = DB::connection()->getDriverName() === 'mysql'
            ? "DATE_FORMAT(payments.payment_date, '%Y-%m')"
            : "strftime('%Y-%m', payments.payment_date)";
        $monthlyIncome = Payment::selectRaw("{$monthExpression} as month, SUM(payments.amount) as paid_total")
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')->join('spaces', 'spaces.id', '=', 'bookings.space_id')
            ->join('properties', 'properties.id', '=', 'spaces.property_id')->where('properties.owner_id', $owner->id)
            ->where('payments.paid_amount', '>', 0)
            ->when($filters['property_id'] ?? null, fn ($q, $id) => $q->where('properties.id', $id))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('payments.payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('payments.payment_date', '<=', $date))
            ->groupBy('month')->orderBy('month')->get();
        $transactions = (clone $payments)->with(['booking.space.property', 'booking.renter'])->latest()->paginate(15);
        $properties = $owner->properties()->orderBy('property_name')->get(['id', 'property_name']);

        return view('owner.reports', compact('stats', 'incomeByProperty', 'monthlyIncome', 'transactions', 'properties', 'filters'));
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        abort_unless($this->ownedBookings($request)->whereKey($booking->id)->exists(), 404);
        $booking->loadMissing('renter', 'space.property');
    }
}
