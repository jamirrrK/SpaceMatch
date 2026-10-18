@extends('layouts.app')
@section('title', 'Viewing requests | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">{{ $ownerSide ? 'Owner workspace' : 'Your rental search' }}</div>
        <h1 class="page-heading mb-1">Viewing requests</h1>
        <p class="small text-muted-soft mb-0">Times are displayed in {{ config('app.timezone') }}. Requests are not confirmed until the owner schedules them.</p>
    </div>
    @if($appointments->isNotEmpty())
        <div class="d-grid gap-3">
            @foreach($appointments as $appointment)
                <article class="panel p-4">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <div class="eyebrow mb-1">{{ $appointment->space?->property?->property_name ?? 'Property unavailable' }}</div>
                            <h2 class="panel-title mb-1">{{ $appointment->space?->space_name ?? 'Space unavailable' }}</h2>
                            <p class="small text-muted-soft mb-2">
                                {{ $ownerSide ? 'Student: '.($appointment->renter?->name ?? 'Account unavailable') : 'Owner: '.($appointment->owner?->name ?? 'Account unavailable') }}
                            </p>
                            <p class="small mb-2">Requested: {{ $appointment->requested_start_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>
                            @if($appointment->scheduled_start_at)<p class="small mb-2">{{ $appointment->status === 'rescheduled' ? 'Proposed new time: ' : 'Scheduled: ' }}{{ $appointment->scheduled_start_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>@endif
                            <span class="badge {{ $appointment->status === 'scheduled' ? 'text-bg-success' : ($appointment->status === 'pending' ? 'text-bg-primary' : 'text-bg-secondary') }}">{{ ucfirst($appointment->status) }}</span>
                        </div>
                        <a class="btn btn-outline-dark" href="{{ route($ownerSide ? 'owner.viewings.show' : 'renter.viewings.show', $appointment) }}">View details</a>
                    </div>
                </article>
            @endforeach
        </div>
        <x-pagination :paginator="$appointments" />
    @else
        <x-empty-state
            title="{{ $ownerSide ? 'No viewing requests yet' : 'No viewings requested yet' }}"
            message="{{ $ownerSide ? 'Student viewing requests will appear here.' : 'Request a viewing from a published, available space to coordinate with its owner.' }}"
            :href="$ownerSide ? route('owner.properties.index') : route('properties.index')"
            :action="$ownerSide ? 'View my listings' : 'Browse rentals'"
        />
    @endif
</div>
@endsection
