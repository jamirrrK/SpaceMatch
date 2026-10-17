@extends('layouts.app')

@section('title', 'Payments | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Payment records" description="A clear view of the payments recorded for your rentals." />
        <div class="table-responsive info-panel overflow-hidden" role="region" tabindex="0" aria-label="Payment records table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        @if(auth()->user()->hasRole('owner'))<th>Record payment</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->booking->space->space_name }}</td>
                            <td>PHP {{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->payment_method ? ucfirst($payment->payment_method) : 'Not recorded' }}</td>
                            <td><x-status-badge :status="$payment->payment_status" /></td>
                            @if(auth()->user()->hasRole('owner'))<td><form method="POST" action="{{ route('owner.payments.update', $payment) }}" class="d-flex flex-wrap gap-2">@csrf @method('PATCH')<select class="form-select form-select-sm" name="payment_status" aria-label="Payment status"><option value="pending" @selected($payment->payment_status === 'pending')>Pending</option><option value="paid" @selected($payment->payment_status === 'paid')>Paid</option></select><input class="form-control form-control-sm" name="payment_method" maxlength="60" placeholder="Cash, transfer…" value="{{ $payment->payment_method }}"><input class="form-control form-control-sm" type="date" name="payment_date" value="{{ $payment->payment_date?->format('Y-m-d') ?? now()->toDateString() }}"><button class="btn btn-outline-dark btn-sm" type="submit">Record</button></form></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->hasRole('owner') ? 5 : 4 }}" class="record-empty"><x-empty-state title="No payment records yet" message="Recorded payments will appear here when available." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$payments" /></div>
    </div>
@endsection
