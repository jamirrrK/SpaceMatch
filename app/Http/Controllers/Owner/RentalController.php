<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingInformationRequest;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Space;
use App\Notifications\RentalWorkflowNotification;
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
        $this->authorizeBooking($request, $booking);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['reject', 'more_details', 'approve'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($booking->status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Only pending rental requests can receive a decision.']);
        }
        if ($data['decision'] === 'more_details' && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Enter the information you need from the renter.']);
        }

        if ($data['decision'] === 'more_details') {
            BookingInformationRequest::create(['booking_id' => $booking->id, 'owner_id' => $request->user()->id, 'question' => $data['reason']]);
            $booking->update(['status' => 'awaiting_renter_information', 'decided_at' => now()]);
            $message = 'The owner requested additional information for your rental request.';
        } elseif ($data['decision'] === 'reject') {
            $booking->update(['status' => 'rejected', 'decision_reason' => $data['reason'] ?? null, 'decided_at' => now()]);
            $message = 'Your rental request was declined.'.(filled($data['reason'] ?? null) ? ' Reason: '.$data['reason'] : '');
        } else {
            DB::transaction(function () use ($booking) {
                $space = Space::whereKey($booking->space_id)->lockForUpdate()->firstOrFail();
                $overlap = Booking::where('space_id', $space->id)->whereIn('status', ['confirmed', 'active'])
                    ->where('start_date', '<', $booking->end_date)->where('end_date', '>', $booking->start_date)
                    ->lockForUpdate()->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['decision' => 'Those dates overlap a confirmed rental.']);
                }
                $booking->update(['status' => 'confirmed', 'decided_at' => now(), 'confirmed_at' => now()]);
                Payment::firstOrCreate(['booking_id' => $booking->id], [
                    'amount' => $booking->agreed_amount ?? $space->price,
                    'payment_status' => 'pending',
                ]);
            });
            $message = 'Your rental request was approved and the booking is confirmed.';
        }
        $booking->renter->notify(new RentalWorkflowNotification($message, route('bookings.index')));

        return back()->with('status', 'Rental request updated and the renter has been notified.');
    }

    public function index(Request $request)
    {
        $bookings = $this->ownedBookings($request)->with(['renter', 'space.property', 'informationRequests'])
            ->latest()->paginate(15);

        return view('owner.rentals.index', compact('bookings'));
    }

    public function complete(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        if (! in_array($booking->status, ['confirmed', 'active'], true) || $booking->end_date->copy()->endOfDay()->isFuture()) {
            throw ValidationException::withMessages(['rental' => 'A rental can be completed only after its end date.']);
        }
        $booking->update(['status' => 'completed', 'completed_at' => now()]);
        $booking->renter->notify(new RentalWorkflowNotification('Your rental has been marked complete.', route('bookings.index')));

        return back()->with('status', 'Rental completed. Its history and payment records have been kept.');
    }

    public function activate(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $today = now()->toDateString();
        if ($booking->status !== 'confirmed' || $booking->start_date->toDateString() > $today || $booking->end_date->toDateString() < $today) {
            throw ValidationException::withMessages(['rental' => 'A rental can be activated only during its confirmed rental period.']);
        }
        $booking->update(['status' => 'active']);

        return back()->with('status', 'Rental is now active and available in your active rentals list.');
    }

    public function recordPayment(Request $request, Payment $payment)
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        $payment->load('booking.space.property');
        abort_unless((int) $payment->booking->space->property->owner_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(['pending', 'paid'])],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'payment_date' => ['nullable', 'date', 'required_if:payment_status,paid'],
        ]);
        $payment->update([
            'payment_status' => $data['payment_status'],
            'payment_method' => $data['payment_method'] ?? null,
            'payment_date' => $data['payment_status'] === 'paid' ? ($data['payment_date'] ?? now()) : null,
        ]);

        return back()->with('status', 'Payment record updated. Only records marked paid are counted as income.');
    }

    public function updateMaintenance(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        $maintenanceRequest->load('booking.space.property', 'booking.renter');
        abort_unless((int) $maintenanceRequest->booking->space->property->owner_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved'])],
            'owner_response' => ['nullable', 'string', 'max:3000'],
        ]);
        $maintenanceRequest->update($data + ['resolved_at' => $data['status'] === 'resolved' ? now() : null]);
        $maintenanceRequest->booking->renter->notify(new RentalWorkflowNotification('Maintenance request status: '.str_replace('_', ' ', $data['status']).'.', route('maintenance.index')));

        return back()->with('status', 'Maintenance request updated and the renter has been notified.');
    }

    public function reports(Request $request)
    {
        $owner = $request->user();
        $filters = $request->validate([
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('owner_id', $owner->id)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['pending', 'awaiting_renter_information', 'confirmed', 'active', 'completed', 'rejected'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid'])],
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
            'active' => (clone $bookings)->whereIn('status', ['confirmed', 'active'])->count(),
            'completed' => (clone $bookings)->where('status', 'completed')->count(),
            'maintenance' => MaintenanceRequest::whereHas('booking.space.property', fn ($q) => $q->where('owner_id', $owner->id))->count(),
            'income' => (clone $payments)->where('payment_status', 'paid')->sum('amount'),
            'outstanding' => (clone $payments)->where('payment_status', 'pending')->sum('amount'),
        ];
        $incomeByProperty = Payment::selectRaw('properties.id as property_id, properties.property_name, SUM(CASE WHEN payments.payment_status = ? THEN payments.amount ELSE 0 END) as paid_income', ['paid'])
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
            ->where('payments.payment_status', 'paid')
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
