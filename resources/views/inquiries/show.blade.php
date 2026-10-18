@extends('layouts.app')
@section('title', 'Inquiry conversation | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <a class="small-link d-inline-block mb-3" href="{{ route(auth()->user()->hasRole('owner') ? 'owner.inquiries.index' : 'renter.inquiries.index') }}">&larr; Back to inquiries</a>
    <section class="panel p-4 mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <div class="eyebrow mb-1">{{ $inquiry->space?->property?->property_name ?? 'Property unavailable' }}</div>
                <h1 class="page-heading mb-2">{{ $inquiry->space?->space_name ?? 'Space unavailable' }}</h1>
                <p class="small text-muted-soft mb-2">
                    {{ auth()->user()->hasRole('owner') ? 'Student: '.($inquiry->renter?->name ?? 'Account unavailable') : 'Property owner: '.($inquiry->owner?->name ?? 'Account unavailable') }}
                </p>
                <span class="badge {{ $inquiry->status === 'open' ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ ucfirst($inquiry->status) }}</span>
            </div>
            @if(auth()->user()->hasRole('owner') && $inquiry->status === 'open')
                <form method="POST" action="{{ route('owner.inquiries.close', $inquiry) }}" data-confirm="Close this inquiry? No further replies can be sent.">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-dark" type="submit">Close inquiry</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel p-4 mb-4" aria-labelledby="inquiry-history-heading">
        <h2 class="panel-title mb-4" id="inquiry-history-heading">Conversation history</h2>
        <ol class="list-unstyled d-grid gap-3 mb-0">
            @forelse($inquiry->activities as $activity)
                <li class="border rounded-3 p-3">
                    @if($activity->activity_type === 'status_changed')
                        <div class="small fw-semibold">{{ $activity->user?->name ?? 'SpaceMatch' }} updated the inquiry</div>
                        <p class="small text-muted-soft mb-1">Status changed from {{ $activity->from_status }} to {{ $activity->to_status }}.</p>
                    @else
                        <div class="small fw-semibold">{{ $activity->user?->name ?? 'Account unavailable' }}</div>
                        <p class="mb-1" style="white-space: pre-wrap">{{ $activity->body }}</p>
                    @endif
                    <time class="small text-muted-soft" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('M j, Y g:i A') }}</time>
                </li>
            @empty
                <li class="small text-muted-soft">No conversation activity.</li>
            @endforelse
        </ol>
    </section>

    @if($inquiry->status === 'open')
        <form class="panel p-4" method="POST" action="{{ route(auth()->user()->hasRole('owner') ? 'owner.inquiries.reply' : 'renter.inquiries.reply', $inquiry) }}">
            @csrf
            <label class="form-label" for="inquiry-reply">Write a reply</label>
            <textarea class="form-control" id="inquiry-reply" name="message" rows="4" maxlength="4000" required>{{ old('message') }}</textarea>
            <div class="form-text">Keep personal contact and sensitive account information out of public listings. This message is visible only to you and the other conversation participant.</div>
            <button class="btn btn-primary-custom mt-3" type="submit">Send reply</button>
        </form>
    @else
        <div class="alert alert-secondary" role="status">This inquiry is closed and can no longer receive replies. Browse other spaces if you still need help finding a rental.</div>
        <a class="btn btn-outline-dark" href="{{ route('properties.index') }}">Browse rentals</a>
    @endif
</div>
@endsection
