<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $ownerId = $request->user()->id;
        $properties = Property::where('owner_id', $ownerId);
        $spaces = Space::whereHas('property', fn (Builder $query) => $query->where('owner_id', $ownerId));
        $bookings = Booking::whereHas('space.property', fn (Builder $query) => $query->where('owner_id', $ownerId));

        $stats = [
            'total_properties' => (clone $properties)->count(),
            'total_spaces' => (clone $spaces)->count(),
            'published_listings' => (clone $spaces)->where('listing_status', 'published')->count(),
            'unpublished_listings' => (clone $spaces)->where('listing_status', 'draft')->count(),
            'available_spaces' => (clone $spaces)->where('availability_status', 'available')->count(),
            'occupied_spaces' => (clone $spaces)->where('availability_status', 'occupied')->count(),
            'pending_requests' => (clone $bookings)->where('status', 'pending')->count(),
            'active_bookings' => (clone $bookings)->whereIn('status', ['confirmed', 'active'])->count(),
            'rental_income' => Payment::whereHas('booking.space.property', fn (Builder $query) => $query->where('owner_id', $ownerId))->sum('amount'),
        ];

        return view('owner.dashboard', [
            'stats' => $stats,
            'properties' => $properties->withCount('spaces')->latest()->take(5)->get(),
            'recentBookings' => $bookings->with(['renter', 'space.property'])->latest()->take(5)->get(),
        ]);
    }
}
