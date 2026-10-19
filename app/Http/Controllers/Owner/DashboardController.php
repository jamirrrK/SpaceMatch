<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\RentalRequest;
use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $ownerId = $request->user()->id;

        $properties = Property::withReviewSummary()->where('owner_id', $ownerId);

        $spaces = Space::whereHas(
            'property',
            fn (Builder $query) => $query->where('owner_id', $ownerId)
        );

        $bookings = Booking::whereHas(
            'space.property',
            fn (Builder $query) => $query->where('owner_id', $ownerId)
        );

        $payments = Payment::whereHas(
            'booking.space.property',
            fn (Builder $query) => $query->where('owner_id', $ownerId)
        );

        $rentalRequests = RentalRequest::visibleTo($request->user());

        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();
        $upcomingEnd = today()->addDays(30);

        $stats = [
            'total_properties' => (clone $properties)->count(),

            'published_properties' => (clone $properties)
                ->where('listing_status', 'published')
                ->count(),

            'draft_properties' => (clone $properties)
                ->where('listing_status', 'draft')
                ->count(),

            'total_spaces' => (clone $spaces)->count(),

            'published_listings' => (clone $spaces)
                ->where('listing_status', 'published')
                ->count(),

            'unpublished_listings' => (clone $spaces)
                ->where('listing_status', 'draft')
                ->count(),

            'available_spaces' => (clone $spaces)
                ->where('availability_status', 'available')
                ->count(),

            'occupied_spaces' => (clone $spaces)
                ->where('availability_status', 'occupied')
                ->count(),

            // Existing rental request workflow.
            'pending_requests' => (clone $rentalRequests)
                ->whereIn('status', [
                    RentalRequest::STATUS_PENDING,
                    RentalRequest::STATUS_MORE_INFORMATION,
                ])
                ->count(),

            // Booking statistics.
            'confirmed_bookings' => (clone $bookings)
                ->where('status', 'confirmed')
                ->count(),

            'active_tenants' => (clone $bookings)
                ->where('status', 'active')
                ->count(),

            // Coworker's active booking statistic.
            'active_bookings' => (clone $bookings)
                ->whereIn('status', ['confirmed', 'active'])
                ->count(),

            // Existing payment collection statistics.
            'collected_total' => (clone $payments)
                ->whereIn('payment_status', ['paid', 'partially_paid'])
                ->sum('paid_amount'),

            'collected_this_month' => PaymentTransaction::query()
                ->whereHas(
                    'payment.booking.space.property',
                    fn (Builder $query) => $query->where('owner_id', $ownerId)
                )
                ->whereBetween('received_at', [$monthStart, $monthEnd])
                ->sum('amount'),

            'rent_due_this_month' => (clone $payments)
                ->where('payment_type', 'monthly_rent')
                ->whereBetween('due_date', [$monthStart, $monthEnd])
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->sum(DB::raw('amount - paid_amount')),

            'outstanding_balance' => (clone $payments)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereRaw('amount > paid_amount')
                ->sum(DB::raw('amount - paid_amount')),

            'overdue_balance' => (clone $payments)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereDate('due_date', '<', today())
                ->whereRaw('amount > paid_amount')
                ->sum(DB::raw('amount - paid_amount')),

            // Upcoming rental activity.
            'upcoming_move_ins' => (clone $bookings)
                ->where('status', 'confirmed')
                ->whereBetween('start_date', [today(), $upcomingEnd])
                ->count(),

            'upcoming_move_outs' => (clone $bookings)
                ->where('status', 'active')
                ->whereBetween('end_date', [today(), $upcomingEnd])
                ->count(),

            // Coworker's inquiry workflow.
            'open_inquiries' => Inquiry::query()
                ->where('owner_id', $ownerId)
                ->where('status', 'open')
                ->count(),

            // Gross scheduled payment amount, not actual collected income.
            'rental_income' => (clone $payments)->sum('amount'),
        ];

        // Monthly payment collection chart.
        $collectionMonths = collect(range(5, 0))
            ->mapWithKeys(function (int $monthsAgo) {
                $month = today()
                    ->startOfMonth()
                    ->subMonths($monthsAgo);

                return [
                    $month->format('Y-m') => [
                        'label' => $month->format('M Y'),
                        'amount' => 0.0,
                    ],
                ];
            });

        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', received_at)"
            : "DATE_FORMAT(received_at, '%Y-%m')";

        $collectionTotals = PaymentTransaction::query()
            ->whereHas(
                'payment.booking.space.property',
                fn (Builder $query) => $query->where('owner_id', $ownerId)
            )
            ->whereBetween(
                'received_at',
                [$monthStart->copy()->subMonths(5), $monthEnd]
            )
            ->selectRaw("$monthExpression as month, SUM(amount) as total")
            ->groupByRaw($monthExpression)
            ->pluck('total', 'month');

        foreach ($collectionTotals as $month => $amount) {
            if ($collectionMonths->has($month)) {
                $monthData = $collectionMonths->get($month);
                $monthData['amount'] = (float) $amount;
                $collectionMonths->put($month, $monthData);
            }
        }

        return view('owner.dashboard', [
            'stats' => $stats,

            'properties' => (clone $properties)
                ->withCount('spaces')
                ->latest()
                ->take(5)
                ->get(),

            'recentBookings' => (clone $bookings)
                ->with(['renter', 'space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'pendingRequests' => (clone $rentalRequests)
                ->whereIn('status', [
                    RentalRequest::STATUS_PENDING,
                    RentalRequest::STATUS_MORE_INFORMATION,
                ])
                ->with(['renter:id,name', 'space.property'])
                ->latest()
                ->limit(5)
                ->get(),

            'upcomingMoveIns' => (clone $bookings)
                ->with(['renter', 'space.property'])
                ->where('status', 'confirmed')
                ->whereBetween('start_date', [today(), $upcomingEnd])
                ->orderBy('start_date')
                ->limit(5)
                ->get(),

            'upcomingMoveOuts' => (clone $bookings)
                ->with(['renter', 'space.property'])
                ->where('status', 'active')
                ->whereBetween('end_date', [today(), $upcomingEnd])
                ->orderBy('end_date')
                ->limit(5)
                ->get(),

            'recentPayments' => PaymentTransaction::query()
                ->whereHas(
                    'payment.booking.space.property',
                    fn (Builder $query) => $query->where('owner_id', $ownerId)
                )
                ->with([
                    'payment.booking.renter',
                    'payment.booking.space.property',
                ])
                ->latest('received_at')
                ->limit(5)
                ->get(),

            'collectionMonths' => $collectionMonths->values(),

            'unreadNotifications' => $request->user()
                ->unreadNotifications()
                ->count(),

            'recentNotifications' => $request->user()
                ->notifications()
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
