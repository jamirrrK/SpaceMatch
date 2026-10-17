@extends('layouts.app')

@section('title', 'Payments | SpaceMatch')

@section('content')
    <div class="container py-5">
        <h1 class="display-6 fw-bold mb-4">Payment records</h1>
        <div class="table-responsive info-panel p-3">
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
                            <td>{{ ucfirst($payment->payment_status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-5">No payment records to show yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $payments->links() }}</div>
    </div>
@endsection
