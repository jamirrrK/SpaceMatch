@extends('layouts.app')
@section('title', 'Reports and analytics | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><div class="eyebrow mb-2">Portfolio insights</div><h1 class="page-heading mb-1">Reports and analytics</h1><p class="text-muted-soft small mb-0">Collections include confirmed ledger transactions only; balances are outstanding obligations, not income.</p></div>
        <a class="btn btn-outline-dark bg-white" href="{{ route('owner.reports.export', request()->query()) }}">Download collections CSV</a>
    </div>
    <form method="GET" action="{{ route('owner.reports.index') }}" class="panel p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label" for="report-property">Property</label><select class="form-select" id="report-property" name="property_id"><option value="">All my properties</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(($filters['property_id'] ?? null) === $property->id)>{{ $property->property_name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="report-from">Collections from</label><input class="form-control" id="report-from" type="date" name="from" value="{{ $filters['from']->toDateString() }}" max="{{ today()->toDateString() }}"></div>
            <div class="col-md-3"><label class="form-label" for="report-to">Through</label><input class="form-control" id="report-to" type="date" name="to" value="{{ $filters['to']->toDateString() }}" max="{{ today()->toDateString() }}"></div>
            <div class="col-md-2"><button class="btn btn-primary-custom w-100" type="submit">Apply</button></div>
        </div>
    </form>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card label="Confirmed collections (range)" :value="'₱'.number_format($collectionTotal, 2)" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Recorded transactions" :value="number_format($collectionCount)" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Outstanding balance" :value="'₱'.number_format($outstandingBalance, 2)" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Overdue balance" :value="'₱'.number_format($overdueBalance, 2)" /></div>
    </div>
    <section class="panel overflow-hidden mb-4">
        <div class="p-4"><h2 class="panel-title mb-1">Outstanding by property</h2><p class="small text-muted-soft mb-0">Unpaid portion of non-cancelled obligations as of today.</p></div>
        <div class="table-wrap" role="region" tabindex="0" aria-label="Outstanding balances by property">
            <table class="table align-middle mb-0"><thead><tr><th scope="col">Property</th><th scope="col">Outstanding (PHP)</th></tr></thead><tbody>
            @forelse($propertyBalances as $propertyBalance)<tr><td>{{ $propertyBalance->property_name }}</td><td>₱{{ number_format((float) $propertyBalance->outstanding_balance, 2) }}</td></tr>
            @empty<tr><td colspan="2" class="record-empty">No outstanding obligations for this selection.</td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
    <section class="panel overflow-hidden">
        <div class="p-4"><h2 class="panel-title mb-1">Confirmed collections</h2><p class="small text-muted-soft mb-0">{{ $filters['from']->format('M j, Y') }} – {{ $filters['to']->format('M j, Y') }} · {{ $collectionCount }} recorded transactions</p></div>
        <div class="table-wrap" role="region" tabindex="0" aria-label="Confirmed collection transactions">
            <table class="table align-middle mb-0"><thead><tr><th scope="col">Received</th><th scope="col">Receipt</th><th scope="col">Property / unit</th><th scope="col">Renter</th><th scope="col">Obligation</th><th scope="col">Amount</th></tr></thead><tbody>
            @forelse($transactions as $transaction)
                <tr><td>{{ $transaction->received_at->format('M j, Y') }}</td><td>{{ $transaction->receipt_number }}</td><td>{{ $transaction->payment->booking->space->property->property_name }}<div class="table-sub">{{ $transaction->payment->booking->space->space_name }}</div></td><td>{{ $transaction->payment->booking->renter->name }}</td><td>{{ str($transaction->payment->payment_type)->replace('_', ' ')->title() }}</td><td>₱{{ number_format((float) $transaction->amount, 2) }}</td></tr>
            @empty<tr><td colspan="6" class="record-empty"><x-empty-state title="No confirmed collections in this date range" message="Only payment transactions confirmed as received are included." /></td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
    <div class="mt-4"><x-pagination :paginator="$transactions" /></div>
</div>
@endsection
