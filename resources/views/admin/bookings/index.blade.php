@extends('layouts.app')
@section('title', 'Bookings | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">Administration</div>
        <h1 class="page-heading mb-1">Rental monitoring</h1>
        <p class="small text-muted-soft mb-0">Review booking activity and current rental lifecycle states.</p>
    </div>

    <form class="panel p-4 mb-4" method="GET" action="{{ route('admin.bookings.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-7">
                <label class="form-label" for="admin-booking-search">Search</label>
                <input class="form-control" type="search" name="q" id="admin-booking-search" value="{{ $filters['q'] ?? '' }}" placeholder="Renter, property, or space name">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="admin-booking-status">Status</label>
                <select class="form-select" name="status" id="admin-booking-status">
                    <option value="">All statuses</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="confirmed" @selected(($filters['status'] ?? '') === 'confirmed')>Confirmed</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary-custom w-100" type="submit">Search</button>
            </div>
        </div>
    </form>

    @if($bookings->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="p-4">
                <h2 class="panel-title mb-0">{{ number_format($bookings->total()) }} {{ $bookings->total() === 1 ? 'booking' : 'bookings' }}</h2>
            </div>
            <div class="table-wrap" role="region" tabindex="0" aria-label="Booking records table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Booking</th>
                            <th scope="col">Renter</th>
                            <th scope="col">Space</th>
                            <th scope="col">Dates</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bookings as $booking)
                            <tr>
                                <td>
                                    <div class="small fw-semibold">#{{ $booking->id }}</div>
                                    <div class="table-sub">{{ $booking->created_at?->format('M d, Y') ?? '—' }}</div>
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $booking->renter?->name ?? 'Unknown renter' }}</div>
                                    <div class="table-sub">{{ $booking->renter?->email ?? '—' }}</div>
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $booking->space?->space_name ?? 'Unknown space' }}</div>
                                    <div class="table-sub">{{ $booking->space?->property?->property_name ?? 'No property linked' }}</div>
                                </td>
                                <td class="table-sub">{{ $booking->start_date?->format('M d, Y') ?? '—' }} to {{ $booking->end_date?->format('M d, Y') ?? '—' }}</td>
                                <td><x-status-badge :status="$booking->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <x-pagination :paginator="$bookings" />
    @else
        <x-empty-state title="No bookings found" message="No booking records match the current filter." :href="route('admin.bookings.index')" action="Clear filters" />
    @endif
</div>
@endsection
