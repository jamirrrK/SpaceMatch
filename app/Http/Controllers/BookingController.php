<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = Booking::visibleTo($request->user())
            ->with(['space.property', 'renter'])
            ->latest()->paginate(15);

        return view('bookings.index', compact('bookings'));
    }
}
