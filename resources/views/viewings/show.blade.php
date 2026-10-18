@extends('layouts.app')
@section('title', 'Viewing details | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <a class="small-link d-inline-block mb-3" href="{{ route(auth()->user()->hasRole('owner') ? 'owner.viewings.index' : 'renter.viewings.index') }}">&larr; Back to viewing requests</a>
    <section class="panel p-4 mb-4">
        <div class="eyebrow mb-1">{{ $appointment->space?->property?->property_name ?? 'Property unavailable' }}</div>
        <h1 class="page-heading mb-2">{{ $appointment->space?->space_name ?? 'Space unavailable' }}</h1>
        <p class="small text-muted-soft mb-2">
            {{ auth()->user()->hasRole('owner') ? 'Student: '.($appointment->renter?->name ?? 'Account unavailable') : 'Property owner: '.($appointment->owner?->name ?? 'Account unavailable') }}
        </p>
        <p class="mb-1"><strong>Requested:</strong> {{ $appointment->requested_start_at->timezone(config('app.timezone'))->format('l, M j, Y g:i A') }} ({{ config('app.timezone') }})</p>
        @if($appointment->scheduled_start_at)<p class="mb-1"><strong>{{ $appointment->status === 'rescheduled' ? 'Proposed new time:' : 'Scheduled:' }}</strong> {{ $appointment->scheduled_start_at->timezone(config('app.timezone'))->format('l, M j, Y g:i A') }} ({{ config('app.timezone') }})</p>@endif
        <p class="mb-1"><strong>Duration:</strong> One hour</p>
        <span class="badge {{ $appointment->status === 'scheduled' ? 'text-bg-success' : ($appointment->status === 'pending' ? 'text-bg-primary' : 'text-bg-secondary') }}">{{ ucfirst($appointment->status) }}</span>
        @if($appointment->response_reason)<p class="mt-3 mb-0"><strong>Owner note:</strong> {{ $appointment->response_reason }}</p>@endif
    </section>

    <section class="panel p-4 mb-4" aria-labelledby="viewing-history-heading">
        <h2 class="panel-title mb-3" id="viewing-history-heading">Status history</h2>
        <ol class="list-unstyled d-grid gap-3 mb-0">
            @foreach($appointment->activities as $activity)
                <li class="border rounded-3 p-3">
                    <div class="small fw-semibold">{{ ucfirst(str_replace('_', ' ', $activity->activity_type)) }}{{ $activity->user ? ' by '.$activity->user->name : '' }}</div>
                    @if($activity->scheduled_start_at)<p class="small mb-1">{{ $activity->scheduled_start_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }} ({{ config('app.timezone') }})</p>@endif
                    @if($activity->reason)<p class="small text-muted-soft mb-1">{{ $activity->reason }}</p>@endif
                    <time class="small text-muted-soft" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('M j, Y g:i A') }}</time>
                </li>
            @endforeach
        </ol>
    </section>

    @if(auth()->user()->hasRole('owner') && $appointment->status === 'pending')
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <form class="panel p-4 h-100" method="POST" action="{{ route('owner.viewings.accept', $appointment) }}" data-confirm="Confirm the student's requested one-hour viewing time?">
                    @csrf
                    @method('PATCH')
                    <h2 class="panel-title mb-2">Accept requested time</h2>
                    <p class="small text-muted-soft">The system checks for overlapping scheduled viewings before confirming.</p>
                    <button class="btn btn-primary-custom" type="submit">Confirm viewing</button>
                </form>
            </div>
            <div class="col-md-6">
                <form class="panel p-4 h-100" method="POST" action="{{ route('owner.viewings.decline', $appointment) }}">
                    @csrf
                    @method('PATCH')
                    <h2 class="panel-title mb-2">Decline request</h2>
                    <label class="form-label" for="decline-reason">Reason for student</label>
                    <textarea class="form-control" id="decline-reason" name="reason" rows="3" minlength="10" maxlength="1000" required></textarea>
                    <button class="btn btn-outline-dark mt-3" type="submit">Decline viewing</button>
                </form>
            </div>
        </div>
    @endif

    @if(auth()->user()->hasRole('owner') && in_array($appointment->status, ['pending', 'scheduled'], true))
        <form class="panel p-4 mb-4" method="POST" action="{{ route('owner.viewings.reschedule', $appointment) }}">
            @csrf
            @method('PUT')
            <h2 class="panel-title mb-2">{{ $appointment->status === 'scheduled' ? 'Reschedule viewing' : 'Propose another time' }}</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="scheduled-start">New time ({{ config('app.timezone') }})</label>
                    <input class="form-control" type="datetime-local" id="scheduled-start" name="scheduled_start_at" min="{{ now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i') }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="reschedule-reason">Note for student</label>
                    <textarea class="form-control" id="reschedule-reason" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea>
                </div>
            </div>
            <button class="btn btn-outline-dark mt-3" type="submit">Save new time</button>
        </form>
    @endif

    @if(auth()->user()->hasRole('renter') && in_array($appointment->status, ['pending', 'scheduled'], true))
        <form method="POST" action="{{ route('renter.viewings.cancel', $appointment) }}" data-confirm="Cancel this viewing request?">
            @csrf
            @method('PATCH')
            <button class="btn btn-outline-dark" type="submit">Cancel viewing</button>
        </form>
    @endif
    @if(auth()->user()->hasRole('renter') && $appointment->status === 'rescheduled')
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('renter.viewings.confirm', $appointment) }}" data-confirm="Confirm the proposed new viewing time?">
                @csrf
                @method('PATCH')
                <button class="btn btn-primary-custom" type="submit">Confirm new time</button>
            </form>
            <form method="POST" action="{{ route('renter.viewings.cancel', $appointment) }}" data-confirm="Cancel this viewing request?">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-dark" type="submit">Cancel viewing</button>
            </form>
        </div>
    @endif
</div>
@endsection
