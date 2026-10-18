<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptController extends Controller
{
    public function show(Request $request, PaymentTransaction $transaction): Response
    {
        $transaction = PaymentTransaction::query()
            ->with(['payment.booking.renter', 'payment.booking.space.property.owner', 'confirmer'])
            ->whereKey($transaction->id)
            ->whereHas('payment.booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->firstOrFail();

        return response()
            ->view('payments.receipt', compact('transaction'))
            ->header('Content-Disposition', 'attachment; filename="'.$transaction->receipt_number.'.html"');
    }
}
