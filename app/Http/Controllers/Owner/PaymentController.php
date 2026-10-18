<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function confirm(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'gcash', 'maya', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $transaction = null;
        $payment = DB::transaction(function () use ($request, $payment, $data, &$transaction): Payment {
            $ownedPayment = Payment::query()
                ->whereKey($payment->id)
                ->whereHas('booking.space.property', fn ($property) => $property->where('owner_id', $request->user()->id))
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ownedPayment->payment_status, ['pending', 'partially_paid'], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment obligation cannot accept additional payments.']);
            }

            if ($ownedPayment->submissions()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['payment' => "Review or reject the renter's pending payment submission before recording another payment."]);
            }

            $remainingCents = (int) round($ownedPayment->outstanding_balance * 100);
            $receivedCents = (int) round(((float) ($data['amount'] ?? $ownedPayment->outstanding_balance)) * 100);
            if ($receivedCents > $remainingCents) {
                throw ValidationException::withMessages(['amount' => 'The received amount cannot exceed the outstanding balance.']);
            }

            $newPaidAmountCents = (int) round((float) $ownedPayment->paid_amount * 100) + $receivedCents;
            $totalCents = (int) round((float) $ownedPayment->amount * 100);
            $ownedPayment->update([
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'paid_amount' => number_format($newPaidAmountCents / 100, 2, '.', ''),
                'payment_status' => $newPaidAmountCents >= $totalCents ? 'paid' : 'partially_paid',
                'confirmed_by' => $request->user()->id,
            ]);

            $transaction = PaymentTransaction::query()->create([
                'payment_id' => $ownedPayment->id,
                'confirmed_by' => $request->user()->id,
                'receipt_number' => 'SMR-'.Str::upper((string) Str::ulid()),
                'amount' => number_format($receivedCents / 100, 2, '.', ''),
                'received_at' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
            ]);

            return $ownedPayment->load('booking.renter', 'booking.space');
        });

        $payment->booking->renter->notify(new RentalWorkflowNotification(
            $payment->payment_status === 'paid' ? 'Payment recorded' : 'Partial payment recorded',
            'Your owner confirmed a manual payment of ₱'.number_format((float) $transaction->amount, 2).' for '.$payment->booking->space->space_name.'.',
            route('payments.receipts.show', $transaction)
        ));

        return back()->with('status', 'Manual payment receipt recorded.');
    }
}
