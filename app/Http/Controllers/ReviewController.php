<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::with(['booking.renter', 'booking.space.property'])
            ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->latest()->paginate(15);

        return view('reviews.index', compact('reviews'));
    }
}
