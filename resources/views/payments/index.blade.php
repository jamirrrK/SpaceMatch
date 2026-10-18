@extends('layouts.app')

@section('title', 'Payments | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Payment records" description="A clear view of the payments recorded for your rentals." />
        <div class="table-responsive info-panel" role="region" tabindex="0" aria-label="Payment records table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Payment</th>
                        <th>Amount / balance / due date</th>
                        <th>Method / reference</th>
                        <th>Status / payment history</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->booking->space->space_name }}<div class="small text-muted-soft">{{ $payment->booking->reference }}</div></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}</td>
                            <td>₱{{ number_format((float) $payment->amount, 2) }}
                                <div class="small text-muted-soft">Received ₱{{ number_format((float) $payment->paid_amount, 2) }}</div>
                                <div class="small text-muted-soft">Balance ₱{{ number_format($payment->outstanding_balance, 2) }}</div>
                                <div class="small text-muted-soft">Due {{ $payment->due_date?->format('M j, Y') ?? 'date not set' }}</div>
                            </td>
                            <td>{{ $payment->payment_method ? ucfirst(str_replace('_', ' ', $payment->payment_method)) : 'Not recorded' }}@if($payment->reference)<div class="small text-muted-soft">Ref: {{ $payment->reference }}</div>@endif</td>
                            <td><x-status-badge :status="$payment->display_status" />
                                @foreach($payment->transactions as $transaction)
                                    <div class="small mt-2">
                                        ₱{{ number_format((float) $transaction->amount, 2) }} received {{ $transaction->received_at->format('M j, Y') }}
                                        <a href="{{ route('payments.receipts.show', $transaction) }}" aria-label="Download receipt {{ $transaction->receipt_number }}">Receipt</a>
                                    </div>
                                @endforeach
                                @if(auth()->user()->role === \App\Models\User::ROLE_OWNER && in_array($payment->payment_status, ['pending', 'partially_paid'], true))
                                    <form class="mt-2" method="POST" action="{{ route('owner.payments.confirm', $payment) }}" data-confirm="Confirm that this manual payment was actually received?">@csrf @method('PATCH')
                                        <label class="form-label small" for="payment-date-{{ $payment->id }}">Date received</label><input class="form-control form-control-sm mb-2" id="payment-date-{{ $payment->id }}" type="date" name="payment_date" max="{{ now()->toDateString() }}" required>
                                        <label class="form-label small" for="payment-method-{{ $payment->id }}">Method</label><select class="form-select form-select-sm mb-2" id="payment-method-{{ $payment->id }}" name="payment_method" required><option value="">Choose method</option><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="gcash">GCash</option><option value="maya">Maya</option><option value="other">Other</option></select>
                                        <label class="form-label small" for="payment-reference-{{ $payment->id }}">Reference (optional)</label><input class="form-control form-control-sm mb-2" id="payment-reference-{{ $payment->id }}" name="reference" maxlength="100">
                                        <label class="form-label small" for="payment-amount-{{ $payment->id }}">Amount actually received</label><input class="form-control form-control-sm mb-2" id="payment-amount-{{ $payment->id }}" type="number" name="amount" min="0.01" max="{{ number_format($payment->outstanding_balance, 2, '.', '') }}" step="0.01" value="{{ number_format($payment->outstanding_balance, 2, '.', '') }}" required>
                                        <button class="btn btn-outline-dark btn-sm" type="submit">Confirm received</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="record-empty"><x-empty-state title="No payment records yet" message="Recorded obligations and owner-confirmed payments will appear here." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="small text-muted-soft mt-3">SpaceMatch records payment information only. Balances change only after the property owner confirms funds received; receipts are available for confirmed amounts only. No payment gateway is connected.</p>
        <div class="mt-4"><x-pagination :paginator="$payments" /></div>
    </div>
@endsection
