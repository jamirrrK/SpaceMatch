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
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->booking->space->space_name }}</td>
                            <td>PHP {{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->payment_method ? ucfirst($payment->payment_method) : 'Not recorded' }}</td>
                            <td><x-status-badge :status="$payment->payment_status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="record-empty"><x-empty-state title="No payment records yet" message="Recorded payments will appear here when available." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$payments" /></div>
    </div>
@endsection
