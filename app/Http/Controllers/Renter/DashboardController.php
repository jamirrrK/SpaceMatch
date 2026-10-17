<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\Space;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $renter = $request->user();
        $renterId = $renter->id;

        $stats = [
            'active_bookings' => Booking::where('renter_id', $renterId)->whereIn('status', ['confirmed', 'active'])->count(),
            'pending_requests' => RentalRequest::where('renter_id', $renterId)->whereIn('status', [
                RentalRequest::STATUS_PENDING,
                RentalRequest::STATUS_MORE_INFORMATION,
            ])->count(),
            'completed_rentals' => Booking::where('renter_id', $renterId)->where('status', 'completed')->count(),
            'outstanding_balances' => Payment::whereHas('booking', fn ($query) => $query->where('renter_id', $renterId))->where('payment_status', 'pending')->sum('amount'),
            'saved_properties' => $renter->savedProperties()->count(),
            'unread_notifications' => $renter->unreadNotifications()->count(),
        ];

        return view('renter.dashboard', [
            'stats' => $stats,
            'bookings' => Booking::with(['space.property'])->where('renter_id', $renterId)->latest()->take(5)->get(),
            'upcomingBooking' => Booking::with(['space.property'])
                ->where('renter_id', $renterId)->where('status', 'confirmed')
                ->whereDate('start_date', '>=', today())->orderBy('start_date')->first(),
            'availableSpaces' => Space::publiclyListed()->with(['property.images', 'images', 'amenities'])
                ->latest('spaces.created_at')->take(3)->get(),
            'recentNotifications' => $renter->notifications()->latest()->take(3)->get(),
        ]);
    }
}
