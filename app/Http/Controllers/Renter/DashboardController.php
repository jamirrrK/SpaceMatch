<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $renterId = Auth::id();

        $stats = [
            'active_bookings' => Booking::where('renter_id', $renterId)->whereIn('status', ['pending', 'confirmed', 'active'])->count(),
            'pending_requests' => Booking::where('renter_id', $renterId)->where('status', 'pending')->count(),
            'completed_rentals' => Booking::where('renter_id', $renterId)->where('status', 'completed')->count(),
            'outstanding_balances' => Payment::whereHas('booking', fn ($query) => $query->where('renter_id', $renterId))->where('payment_status', 'pending')->sum('amount'),
            'saved_spaces' => Auth::user()->savedSpaces()->count(),
        ];

        return view('renter.dashboard', [
            'stats' => $stats,
            'bookings' => Booking::with(['space.property'])->where('renter_id', $renterId)->latest()->take(5)->get(),
        ]);
    }
}
