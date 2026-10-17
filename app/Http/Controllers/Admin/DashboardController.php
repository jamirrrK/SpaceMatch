<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'total_owners' => User::where('role', 'owner')->count(),
            'total_renters' => User::where('role', 'renter')->count(),
            'total_properties' => Property::count(),
            'total_spaces' => Space::count(),
            'total_listings' => Space::where('listing_status', 'published')->count(),
            'active_properties' => Property::whereIn('status', ['active', 'available'])->count(),
            'inactive_properties' => Property::where('status', 'inactive')->count(),
            'active_bookings' => Booking::whereIn('status', ['pending', 'confirmed', 'active'])->count(),
            'pending_requests' => Booking::where('status', 'pending')->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentUsers' => User::latest()->take(5)->get(),
            'recentProperties' => Property::with('owner')->latest()->take(5)->get(),
            'recentBookings' => Booking::with(['renter', 'space'])->latest()->take(5)->get(),
        ]);
    }
}
