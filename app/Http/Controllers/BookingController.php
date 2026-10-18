<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
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
