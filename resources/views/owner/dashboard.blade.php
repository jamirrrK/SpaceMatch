@extends('layouts.app')
@section('title', 'Owner overview | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><div class="eyebrow mb-2">Owner workspace</div><h1 class="page-heading mb-1">Your portfolio at a glance.</h1><p class="text-muted-soft small mb-0">Property operations, upcoming rentals and recorded cash collections.</p></div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-dark bg-white" href="{{ route('owner.properties.index') }}">Manage properties</a><a class="btn btn-primary-custom" href="{{ route('owner.properties.create') }}">+ Add property</a></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card label="Properties" :value="number_format($stats['total_properties'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Published properties" :value="number_format($stats['published_properties'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Rental spaces" :value="number_format($stats['total_spaces'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Available spaces" :value="number_format($stats['available_spaces'])" /></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card label="Pending requests" :value="number_format($stats['pending_requests'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Confirmed bookings" :value="number_format($stats['confirmed_bookings'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Active tenants" :value="number_format($stats['active_tenants'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Unread notifications" :value="number_format($unreadNotifications)" /></div>
    </div>
    <section class="panel p-4 mb-4" aria-labelledby="owner-financial-summary">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div><h2 class="panel-title mb-1" id="owner-financial-summary">Financial snapshot</h2><p class="small text-muted-soft mb-0">Collections count confirmed funds only. Outstanding balances are obligations, not revenue.</p></div>
            <a class="small-link" href="{{ route('owner.reports.index') }}">Open reports ↗</a>
        </div>
        <dl class="platform-summary mb-0">
            <div><dt>Confirmed collections</dt><dd>₱{{ number_format((float) $stats['collected_total'], 2) }}</dd></div>
            <div><dt>Collected this month</dt><dd>₱{{ number_format((float) $stats['collected_this_month'], 2) }}</dd></div>
            <div><dt>Rent due this month</dt><dd>₱{{ number_format((float) $stats['rent_due_this_month'], 2) }}</dd></div>
            <div><dt>Outstanding balance</dt><dd>₱{{ number_format((float) $stats['outstanding_balance'], 2) }}</dd></div>
            <div><dt>Overdue balance</dt><dd>₱{{ number_format((float) $stats['overdue_balance'], 2) }}</dd></div>
            <div><dt>Upcoming move-ins / move-outs</dt><dd>{{ number_format($stats['upcoming_move_ins']) }} / {{ number_format($stats['upcoming_move_outs']) }}</dd></div>
        </dl>
        <div class="mt-4">
            <h3 class="h6 mb-3">Recorded collections — last six months</h3>
            @php($peakCollection = max(1, (float) $collectionMonths->max('amount')))
            <div class="d-grid gap-2" aria-label="Monthly confirmed collections chart">
                @foreach($collectionMonths as $month)
                    <div class="d-flex align-items-center gap-3">
                        <span class="small text-muted-soft" style="width:4.5rem">{{ $month['label'] }}</span>
                        <div class="progress flex-grow-1" role="img" aria-label="{{ $month['label'] }}: ₱{{ number_format($month['amount'], 2) }}">
                            <div class="progress-bar bg-primary" style="width: {{ min(100, ($month['amount'] / $peakCollection) * 100) }}%"></div>
                        </div>
                        <span class="small fw-semibold text-nowrap">₱{{ number_format($month['amount'], 2) }}</span>
                    </div>
                @endforeach
            </div>
            <p class="small text-muted-soft mt-2 mb-0">Monthly bars use payment transactions recorded in the payment ledger; historical obligations without transaction entries are not charted.</p>
        </div>
    </section>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-dark bg-white" href="{{ route('owner.requests.index') }}">Review requests <span class="badge text-bg-light">{{ number_format($stats['pending_requests']) }}</span></a>
        <a class="btn btn-outline-dark bg-white" href="{{ route('owner.tenants.index') }}">Manage tenants</a>
        <a class="btn btn-outline-dark bg-white" href="{{ route('owner.spaces.index') }}">Manage spaces</a>
        <a class="btn btn-outline-dark bg-white" href="{{ route('owner.spaces.create') }}">+ Add rental space</a>
    </div>
    <section class="panel overflow-hidden mb-4">
        <div class="d-flex justify-content-between align-items-center gap-3 p-4"><h2 class="panel-title mb-0">Your properties</h2><a class="small-link" href="{{ route('owner.properties.index') }}">View all ↗</a></div>
        @if($properties->isNotEmpty())
            <div class="table-wrap" role="region" tabindex="0" aria-label="Your properties table">
                <table class="table mb-0"><thead><tr><th scope="col">Property</th><th scope="col">Spaces</th><th scope="col">Property status</th><th scope="col">Listing</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead><tbody>
                @foreach($properties as $property)
                    <tr><td><a class="table-name" href="{{ route('owner.properties.show', $property) }}">{{ $property->property_name }}</a><div class="table-sub">{{ $property->city }}, {{ $property->province }}</div></td><td>{{ $property->spaces_count }}</td><td><x-status-badge :status="$property->status" /></td><td><x-status-badge :status="$property->listing_status" /></td><td><a class="small-link" href="{{ route('owner.properties.show', $property) }}">Manage</a></td></tr>
                @endforeach
                </tbody></table>
            </div>
        @else
            <div class="p-4 pt-0"><x-empty-state title="Your portfolio starts here" message="Add a property, create its rental spaces and publish eligible listings." :href="route('owner.properties.create')" action="Add your first property" /></div>
        @endif
    </section>
    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <section class="panel overflow-hidden h-100">
                <div class="d-flex justify-content-between align-items-center gap-3 p-4"><h2 class="panel-title mb-0">Requests awaiting your response</h2><a class="small-link" href="{{ route('owner.requests.index') }}">Review all ↗</a></div>
                @forelse($pendingRequests as $rentalRequest)
                    <div class="px-4 py-3 border-top">
                        <div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ $rentalRequest->renter->name }} · {{ $rentalRequest->space->property->property_name }}</strong><x-status-badge :status="$rentalRequest->status" /></div>
                        <div class="small text-muted-soft">{{ $rentalRequest->space->space_name }} · Move-in {{ $rentalRequest->move_in_date->format('M j, Y') }} · {{ $rentalRequest->occupants }} occupant(s)</div>
                    </div>
                @empty<div class="px-4 pb-4 small text-muted-soft">No rental requests need a decision.</div>@endforelse
            </section>
        </div>
        <div class="col-xl-5">
            <section class="panel overflow-hidden h-100">
                <div class="d-flex justify-content-between align-items-center gap-3 p-4"><h2 class="panel-title mb-0">Recent notifications</h2><a class="small-link" href="{{ route('notifications.index') }}">Notification history ↗</a></div>
                @forelse($recentNotifications as $notification)
                    <div class="px-4 py-3 border-top">
                        <div class="fw-semibold">{{ $notification->data['title'] ?? 'SpaceMatch update' }}</div>
                        <div class="small text-muted-soft">{{ $notification->data['message'] ?? '' }}</div>
                        <time class="small text-muted-soft" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                @empty<div class="px-4 pb-4 small text-muted-soft">Your rental notifications will appear here.</div>@endforelse
            </section>
        </div>
    </div>
    <section class="panel overflow-hidden mb-4">
        <div class="d-flex justify-content-between align-items-center gap-3 p-4"><h2 class="panel-title mb-0">Recent booking activity</h2><a class="small-link" href="{{ route('bookings.index') }}">Manage bookings ↗</a></div>
        @forelse($recentBookings as $booking)
            <div class="px-4 py-3 border-top">
                <div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ $booking->reference }} · {{ $booking->renter->name }}</strong><x-status-badge :status="$booking->status" /></div>
                <div class="small text-muted-soft">{{ $booking->space->property->property_name }} · {{ $booking->space->space_name }} · {{ $booking->created_at->diffForHumans() }}</div>
            </div>
        @empty<div class="px-4 pb-4 small text-muted-soft">Booking activity will appear here after a request is approved.</div>@endforelse
    </section>
    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="panel overflow-hidden h-100">
                <div class="d-flex justify-content-between align-items-center p-4"><h2 class="panel-title mb-0">Upcoming move-ins</h2><a class="small-link" href="{{ route('owner.tenants.index') }}">Tenants ↗</a></div>
                @forelse($upcomingMoveIns as $booking)
                    <div class="px-4 py-3 border-top"><div class="fw-semibold">{{ $booking->renter->name }} · {{ $booking->space->property->property_name }}</div><div class="small text-muted-soft">{{ $booking->space->space_name }} · {{ $booking->start_date->format('M j, Y') }}</div></div>
                @empty<div class="px-4 pb-4 small text-muted-soft">No confirmed move-ins in the next 30 days.</div>@endforelse
            </section>
        </div>
        <div class="col-xl-6">
            <section class="panel overflow-hidden h-100">
                <div class="d-flex justify-content-between align-items-center p-4"><h2 class="panel-title mb-0">Upcoming move-outs</h2><a class="small-link" href="{{ route('bookings.index') }}">Bookings ↗</a></div>
                @forelse($upcomingMoveOuts as $booking)
                    <div class="px-4 py-3 border-top"><div class="fw-semibold">{{ $booking->renter->name }} · {{ $booking->space->property->property_name }}</div><div class="small text-muted-soft">{{ $booking->space->space_name }} · {{ $booking->end_date->format('M j, Y') }}</div></div>
                @empty<div class="px-4 pb-4 small text-muted-soft">No active rentals ending in the next 30 days.</div>@endforelse
            </section>
        </div>
    </div>
    <section class="panel overflow-hidden mb-4">
        <div class="d-flex justify-content-between align-items-center gap-3 p-4"><h2 class="panel-title mb-0">Recent confirmed payments</h2><a class="small-link" href="{{ route('payments.index') }}">All payment records ↗</a></div>
        <div class="table-wrap" role="region" tabindex="0" aria-label="Recent confirmed payment transactions">
            <table class="table align-middle mb-0"><thead><tr><th scope="col">Received</th><th scope="col">Renter</th><th scope="col">Property</th><th scope="col">Receipt</th><th scope="col">Amount received</th></tr></thead><tbody>
            @forelse($recentPayments as $transaction)
                <tr><td>{{ $transaction->received_at->format('M j, Y') }}</td><td>{{ $transaction->payment->booking->renter->name }}</td><td>{{ $transaction->payment->booking->space->property->property_name }}</td><td><a href="{{ route('payments.receipts.show', $transaction) }}">{{ $transaction->receipt_number }}</a></td><td>₱{{ number_format((float) $transaction->amount, 2) }}</td></tr>
            @empty<tr><td colspan="5" class="record-empty">No confirmed payment transactions recorded yet.</td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
</div>
@endsection
