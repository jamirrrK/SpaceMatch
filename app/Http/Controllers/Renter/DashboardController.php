<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\RentalRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $renter = $request->user();
        $renterId = $renter->id;

        $stats = [
            'active_bookings' => Booking::where('renter_id', $renterId)
                ->whereIn('status', ['confirmed', 'active'])
                ->count(),

            'pending_requests' => RentalRequest::where('renter_id', $renterId)
                ->whereIn('status', [
                    RentalRequest::STATUS_PENDING,
                    RentalRequest::STATUS_MORE_INFORMATION,
                ])
                ->count(),

            'completed_rentals' => Booking::where('renter_id', $renterId)
                ->where('status', 'completed')
                ->count(),

            'outstanding_balances' => Payment::whereHas(
                'booking',
                fn ($query) => $query->where('renter_id', $renterId)
            )
                ->where('payment_status', 'pending')
                ->sum('amount'),

            'saved_properties' => $renter->savedProperties()->count(),

            'saved_spaces' => $renter->savedSpaces()->count(),

            'open_inquiries' => Inquiry::where('renter_id', $renterId)
                ->where('status', 'open')
                ->count(),

            'unread_notifications' => $renter->unreadNotifications()->count(),
        ];

        return view('renter.dashboard', [
            'stats' => $stats,

            'recentBookings' => Booking::where('renter_id', $renterId)
                ->with(['space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'recentRequests' => RentalRequest::where('renter_id', $renterId)
                ->with(['space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'recentPayments' => Payment::whereHas(
                'booking',
                fn ($query) => $query->where('renter_id', $renterId)
            )
                ->with(['booking.space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'recentNotifications' => $renter->notifications()
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
