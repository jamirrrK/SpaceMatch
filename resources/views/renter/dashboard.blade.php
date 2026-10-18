@extends('layouts.app')
@section('title', auth()->user()->is_student ? 'Student dashboard | SpaceMatch' : 'Renter dashboard | SpaceMatch')
@section('content')
@php
    $stats = $stats ?? [];
    $bookings = $bookings ?? ($recentBookings ?? collect());
    $recentNotifications = $recentNotifications ?? collect();
    $availableSpaces = $availableSpaces ?? collect();
    $upcomingBooking = $upcomingBooking ?? null;
@endphp

<div class="dashboard-content py-4 py-lg-5">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="eyebrow mb-2">{{ auth()->user()->is_student ? 'Student renter workspace' : 'Your SpaceMatch' }}</div>
            <h1 class="page-heading mb-1">Welcome back, {{ auth()->user()->name }}.</h1>
            <p class="text-muted-soft small mb-0">Contact verification complete. Manage your rental plans from here.</p>
        </div>
        <a class="btn btn-primary-custom" href="{{ route('properties.index') }}">Explore rental spaces</a>
    </header>

    @include('renter.student-overview')
    <form class="panel p-3 p-lg-4 mb-4" method="GET" action="{{ route('properties.index') }}" role="search" aria-label="Quick rental search">
        <div class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label" for="dashboard-search">What are you looking for?</label><input class="form-control" id="dashboard-search" type="search" name="q" placeholder="Property, space, or amenity"></div>
            <div class="col-md-5"><label class="form-label" for="dashboard-location">Where?</label><input class="form-control" id="dashboard-location" type="search" name="location" placeholder="City, barangay, or province"></div>
            <div class="col-md-2"><button class="btn btn-primary-custom w-100" type="submit">Search</button></div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach(['active_bookings' => 'Active bookings', 'pending_requests' => 'Pending requests', 'completed_rentals' => 'Completed rentals', 'saved_properties' => 'Saved properties', 'saved_spaces' => 'Saved spaces'] as $key => $label)
            <div class="col-6 col-lg-3"><x-stat-card :label="$label" :value="number_format($stats[$key] ?? 0)" /></div>
        @endforeach
        <div class="col-6 col-lg-3"><x-stat-card label="Outstanding balances" :value="'₱'.number_format($stats['outstanding_balances'] ?? 0, 2)" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Unread updates" :value="number_format($stats['unread_notifications'] ?? 0)" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Open inquiries" :value="number_format($stats['open_inquiries'] ?? 0)" /></div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-dark" href="{{ route('renter.requests.index') }}">My rental requests</a>
        <a class="btn btn-outline-dark" href="{{ route('renter.saved-properties.index') }}">Saved properties</a>
        <a class="btn btn-outline-dark" href="{{ route('renter.saved-spaces.index') }}">Saved spaces</a>
        <a class="btn btn-outline-dark" href="{{ route('renter.inquiries.index') }}">Inquiries ({{ number_format($stats['open_inquiries'] ?? 0) }})</a>
        <a class="btn btn-outline-dark" href="{{ route('renter.viewings.index') }}">Viewings</a>
        <a class="btn btn-outline-dark" href="{{ route('renter.preferences.edit') }}">Housing preferences</a>
        <a class="btn btn-outline-dark" href="{{ route('bookings.index') }}">My bookings</a>
        <a class="btn btn-outline-dark" href="{{ route('maintenance.index') }}">Maintenance</a>
        <a class="btn btn-outline-dark" href="{{ route('payments.index') }}">Payment records</a>
        <a class="btn btn-outline-dark" href="{{ route('notifications.index') }}">Notifications</a>
    </div>

    @if(!empty($upcomingBooking))
        <section class="panel p-4 mb-4" aria-labelledby="upcoming-move-title">
            <div class="eyebrow mb-2">Upcoming move-in</div>
            <h2 class="panel-title mb-1" id="upcoming-move-title">{{ $upcomingBooking->space?->property?->property_name ?? 'Property' }} · {{ $upcomingBooking->space?->space_name ?? 'Space' }}</h2>
            <p class="text-muted-soft mb-2">{{ $upcomingBooking->start_date->format('M j, Y') }} – {{ $upcomingBooking->end_date->format('M j, Y') }}</p>
            <a class="small-link" href="{{ route('bookings.show', $upcomingBooking) }}">View booking details</a>
        </section>
    @endif

    <section class="dashboard-discovery mb-4">
        <x-marketing-image name="next_home_promo" alt="Illustration of a welcoming home" sizes="130px" />
        <div>
            <div class="eyebrow mb-2">Room for your next chapter</div>
            <h2>New neighborhood. New possibilities.</h2>
            <p>Find a space that works for your city, your budget, and your everyday life.</p>
            <a class="small-link" href="{{ route('properties.index') }}">Discover available rentals <span aria-hidden="true">&rarr;</span></a>
        </div>
    </section>

    @if($recentNotifications->isNotEmpty())
        <section class="panel p-4 mb-4" aria-labelledby="recent-updates-title">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <h2 class="panel-title mb-0" id="recent-updates-title">Recent updates</h2>
                <a class="small-link" href="{{ route('notifications.index') }}">All notifications</a>
            </div>
            <ul class="list-unstyled mb-0">
                @foreach($recentNotifications as $notification)
                    <li class="border-top py-3">
                        <strong>{{ $notification->data['title'] ?? 'SpaceMatch update' }}</strong>
                        <p class="small mb-1">{{ $notification->data['message'] ?? '' }}</p>
                        <time class="small text-muted-soft" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('M j, Y g:i A') }}</time>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('renter.activity-overview')
    @if($availableSpaces->isNotEmpty())
        <section class="mb-4" aria-labelledby="available-spaces-title">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <h2 class="panel-title mb-0" id="available-spaces-title">Matches for your housing preferences</h2>
                <a class="small-link" href="{{ route('properties.index') }}">Browse all</a>
            </div>
            <div class="row g-4">
                @foreach($availableSpaces as $space)
                    <div class="col-md-6 col-xl-4"><x-space-card :space="$space" /></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel overflow-hidden">
        <div class="d-flex justify-content-between align-items-center gap-3 p-4">
            <h2 class="panel-title mb-0">Recent bookings</h2>
            <a class="small-link" href="{{ route('bookings.index') }}">View bookings ↗</a>
        </div>
        @if($bookings->isNotEmpty())
            <div class="table-wrap" role="region" tabindex="0" aria-label="Scrollable records table">
                <table class="table mb-0">
                    <thead><tr><th scope="col">Rental space</th><th scope="col">Property</th><th scope="col">Status</th><th scope="col">Added</th></tr></thead>
                    <tbody>
                        @foreach($bookings as $booking)
                            <tr>
                                <td class="fw-semibold"><a href="{{ route('bookings.show', $booking) }}">{{ $booking->space?->space_name ?? 'Space unavailable' }}</a></td>
                                <td>{{ $booking->space?->property?->property_name ?? 'Property unavailable' }}</td>
                                <td><x-status-badge :status="$booking->status" /></td>
                                <td class="small text-muted-soft">{{ $booking->created_at?->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-4 pt-0"><x-empty-state title="Your next space is waiting" message="Discover available rentals by city, budget, capacity, and amenities." :href="route('properties.index')" action="Find a space" /></div>
        @endif
    </section>
</div>
@endsection
