@extends('layouts.app')
@section('title', 'Tenants | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">Resident directory</div>
        <h1 class="page-heading mb-1">Tenants and upcoming move-ins</h1>
        <p class="text-muted-soft small mb-0">Only renters on your confirmed or active bookings appear here.</p>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6"><x-stat-card label="Active tenants" :value="number_format($totalActive)" /></div>
        <div class="col-6"><x-stat-card label="Confirmed bookings" :value="number_format($totalConfirmed)" /></div>
    </div>
    <form method="GET" action="{{ route('owner.tenants.index') }}" class="panel p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label" for="tenant-search">Search renters, property, unit or booking</label>
                <input class="form-control" id="tenant-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" type="search">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="tenant-status">Booking status</label>
                <select class="form-select" id="tenant-status" name="status">
                    <option value="">All current bookings</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active tenant</option>
                    <option value="confirmed" @selected(($filters['status'] ?? '') === 'confirmed')>Confirmed / move-in pending</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary-custom flex-grow-1" type="submit">Filter</button>
                <a class="btn btn-outline-dark" href="{{ route('owner.tenants.index') }}">Clear</a>
            </div>
        </div>
    </form>
    <section class="panel overflow-hidden">
        <div class="table-wrap" role="region" tabindex="0" aria-label="Tenant booking records">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Tenant</th><th scope="col">Property / space</th><th scope="col">Booking</th><th scope="col">Dates</th><th scope="col">Monthly rent</th><th scope="col">Outstanding</th><th scope="col">Status</th></tr></thead>
                <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>{{ $booking->renter->name }}</td>
                        <td>{{ $booking->space->property->property_name }}<div class="table-sub">{{ $booking->space->space_name }}</div></td>
                        <td>{{ $booking->reference }}</td>
                        <td>{{ $booking->start_date->format('M j, Y') }}<div class="table-sub">to {{ $booking->end_date->format('M j, Y') }}</div></td>
                        <td>₱{{ number_format((float) $booking->monthly_rent, 2) }}</td>
                        <td>₱{{ number_format($booking->payments->sum(fn ($payment) => $payment->outstanding_balance), 2) }}</td>
                        <td><x-status-badge :status="$booking->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="record-empty"><x-empty-state title="No matching tenants" message="Confirmed move-ins and active renters for your properties will appear here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4"><x-pagination :paginator="$bookings" /></div>
</div>
@endsection
