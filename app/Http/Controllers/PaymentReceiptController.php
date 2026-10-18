<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
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

        return Pdf::loadView('payments.receipt', compact('transaction'))
            ->setPaper('a4')
            ->download($transaction->receipt_number.'.pdf');
    }
}
