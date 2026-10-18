<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::with([
            'booking.space.property',
            'confirmer',
            'transactions',
            'submissions.submitter',
            'submissions.reviewer',
        ])
            ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->latest()->paginate(15);

        return view('payments.index', compact('payments'));
    }
}
