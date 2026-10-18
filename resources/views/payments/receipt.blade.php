<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment receipt {{ $transaction->receipt_number }}</title>
    <style>
        body { color: #222; font: 16px/1.5 Arial, sans-serif; margin: 3rem auto; max-width: 680px; padding: 0 1rem; }
        h1 { border-bottom: 2px solid #222; padding-bottom: .75rem; }
        dt { font-weight: 700; margin-top: 1rem; }
        dd { margin-left: 0; }
        .note { border-top: 1px solid #bbb; margin-top: 2rem; padding-top: 1rem; }
    </style>
</head>
<body>
    <main>
        <h1>SpaceMatch payment receipt</h1>
        <dl>
            <dt>Receipt number</dt><dd>{{ $transaction->receipt_number }}</dd>
            <dt>Received</dt><dd>{{ $transaction->received_at->format('M j, Y') }}</dd>
            <dt>Received by</dt><dd>{{ $transaction->confirmer->name }}</dd>
            <dt>Renter</dt><dd>{{ $transaction->payment->booking->renter->name }}</dd>
            <dt>Property and space</dt><dd>{{ $transaction->payment->booking->space->property->property_name }} — {{ $transaction->payment->booking->space->space_name }}</dd>
            <dt>Booking reference</dt><dd>{{ $transaction->payment->booking->reference }}</dd>
            <dt>Obligation</dt><dd>{{ ucfirst(str_replace('_', ' ', $transaction->payment->payment_type)) }}</dd>
            <dt>Amount received</dt><dd>₱{{ number_format((float) $transaction->amount, 2) }}</dd>
            <dt>Payment method</dt><dd>{{ ucfirst(str_replace('_', ' ', $transaction->payment_method)) }}</dd>
            @if($transaction->reference)<dt>Reference</dt><dd>{{ $transaction->reference }}</dd>@endif
        </dl>
        <p class="note">This receipt records a manually confirmed payment. SpaceMatch did not process or guarantee the transfer.</p>
    </main>
</body>
</html>
