@extends('layouts.app')
@section('title', 'Platform overview | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="eyebrow mb-2">Administration</div>
            <h1 class="page-heading mb-1">Platform overview.</h1>
            <p class="text-muted-soft small mb-0">Real-time view of users, listings, bookings, and operational health.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-dark" href="{{ route('admin.users.index') }}">Manage users</a>
            <a class="btn btn-primary-custom" href="{{ route('admin.properties.index') }}">Review properties</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            'total_users' => 'Total users',
            'total_owners' => 'Property owners',
            'total_renters' => 'Renters',
            'owners_waiting_verification' => 'Pending owner verification',
            'total_properties' => 'Total properties',
            'pending_property_reviews' => 'Pending reviews',
            'approved_properties' => 'Published listings',
            'total_spaces' => 'Rental spaces',
            'available_spaces' => 'Available spaces',
            'pending_booking_requests' => 'Pending requests',
            'confirmed_bookings' => 'Confirmed bookings',
            'active_rentals' => 'Active rentals',
            'total_payments' => 'Recorded payments',
            'pending_payments' => 'Pending payments',
            'unresolved_reports' => 'Open reports',
        ] as $key => $label)
            <div class="col-6 col-xl-3">
                <x-stat-card :label="$label" :value="number_format($stats[$key] ?? 0)" />
            </div>
        @endforeach
    </div>

    <section class="panel p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 class="panel-title mb-0">Key platform health</h2>
            <a class="small-link" href="{{ route('admin.audit-logs.index') }}">Open audit trail &rarr;</a>
        </div>
        <dl class="platform-summary mb-0">
            @foreach([
                'total_listings' => 'Published listings',
                'active_properties' => 'Active properties',
                'inactive_properties' => 'Inactive properties',
                'pending_requests' => 'Pending booking requests',
                'total_payments' => 'Payments recorded',
                'unresolved_reports' => 'Open support issues',
            ] as $key => $label)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ number_format($stats[$key] ?? 0) }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <div class="row g-4">
        <div class="col-xl-6">
            <section class="panel p-4 mb-4">
                <h2 class="panel-title mb-3">New community members</h2>
                @forelse($recentUsers as $user)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="small fw-semibold">{{ $user->name }}</div>
                            <div class="table-sub">{{ $user->email }}</div>
                        </div>
                        <span class="amenity-chip">{{ ucfirst($user->role) }}</span>
                    </div>
                @empty
                    <p class="small text-muted-soft mb-0">No users to display.</p>
                @endforelse
            </section>
        </div>

        <div class="col-xl-6">
            <section class="panel p-4 mb-4">
                <h2 class="panel-title mb-3">Recent bookings</h2>
                @forelse($recentBookings as $booking)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="small fw-semibold">{{ $booking->space?->space_name ?? 'Space unavailable' }}</div>
                            <div class="table-sub">{{ $booking->renter?->name ?? 'Renter unavailable' }}</div>
                        </div>
                        <x-status-badge :status="$booking->status" />
                    </div>
                @empty
                    <p class="small text-muted-soft mb-0">No booking records to display.</p>
                @endforelse
            </section>
        </div>
    </div>

    <section class="panel overflow-hidden mb-4">
        <div class="d-flex justify-content-between align-items-center p-4">
            <h2 class="panel-title mb-0">Recently added properties</h2>
            <a class="small-link" href="{{ route('admin.properties.index') }}">View all ↗</a>
        </div>
        @if($recentProperties->isNotEmpty())
            <div class="table-wrap" role="region" tabindex="0" aria-label="Property records table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Property</th>
                            <th scope="col">Owner</th>
                            <th scope="col">Status</th>
                            <th scope="col">Listing</th>
                            <th scope="col" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($recentProperties as $property)
                        <tr>
                            <td>
                                <a class="table-name" href="{{ route('admin.properties.show', $property) }}">{{ $property->property_name }}</a>
                                <div class="table-sub">{{ $property->city }}, {{ $property->province }}</div>
                            </td>
                            <td>{{ $property->owner?->name ?? 'Owner unavailable' }}</td>
                            <td><x-status-badge :status="$property->status" /></td>
                            <td><x-status-badge :status="$property->listing_status" /></td>
                            <td><a class="small-link" href="{{ route('admin.properties.show', $property) }}">Review</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-4 pt-0"><x-empty-state title="No properties yet" message="Properties will appear here as owners add them." /></div>
        @endif
    </section>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="panel p-4">
                <h2 class="panel-title mb-3">Recent payments</h2>
                @forelse($recentPayments as $payment)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="small fw-semibold">{{ number_format((float) $payment->amount, 2) }}</div>
                            <div class="table-sub">{{ $payment->booking?->renter?->name ?? 'Renter unavailable' }}</div>
                        </div>
                        <x-status-badge :status="$payment->payment_status" />
                    </div>
                @empty
                    <p class="small text-muted-soft mb-0">No payment records yet.</p>
                @endforelse
            </section>
        </div>

        <div class="col-lg-4">
            <section class="panel p-4">
                <h2 class="panel-title mb-3">Pending support issues</h2>
                @forelse($recentMaintenanceRequests as $request)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="small fw-semibold">{{ $request->booking?->renter?->name ?? 'Guest' }}</div>
                            <div class="table-sub">{{ Str::limit($request->description ?? 'No description', 48) }}</div>
                        </div>
                        <x-status-badge :status="$request->status" />
                    </div>
                @empty
                    <p class="small text-muted-soft mb-0">No support issues to review.</p>
                @endforelse
            </section>
        </div>

        <div class="col-lg-4">
            <section class="panel p-4">
                <h2 class="panel-title mb-3">Recent admin actions</h2>
                @forelse($recentAuditLogs as $log)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="small fw-semibold">{{ $log->action }}</div>
                            <div class="table-sub">{{ $log->user?->name ?? 'System' }}</div>
                        </div>
                        <span class="small text-muted-soft">{{ $log->created_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                @empty
                    <p class="small text-muted-soft mb-0">No recent admin actions.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
