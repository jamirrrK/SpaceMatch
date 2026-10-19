@extends('layouts.app')
@section('title', 'Conversation | SpaceMatch')
@section('content')
@php
    $ownerSide = auth()->user()->hasRole('owner');
    $routePrefix = $ownerSide ? 'owner' : 'renter';
    $latestActivityId = (int) $inquiry->activities->max('id');
@endphp
<div class="inquiry-workspace">
    <a class="small-link d-inline-block mb-3" href="{{ route($routePrefix.'.inquiries.index') }}">&larr; Back to messages</a>
    <section class="panel inquiry-conversation"
        data-inquiry-messenger
        data-updates-url="{{ route($routePrefix.'.inquiries.updates', $inquiry) }}"
        data-current-user-id="{{ auth()->id() }}"
        data-after-id="{{ $latestActivityId }}"
        data-unread-count="{{ $unreadInquiryCount ?? 0 }}">
        <header class="inquiry-conversation-header">
            <div class="min-w-0">
                <div class="eyebrow mb-1">{{ $inquiry->space?->property?->property_name ?? 'Property unavailable' }}</div>
                <h1 class="panel-title mb-1">{{ $inquiry->space?->space_name ?? 'Space unavailable' }}</h1>
                <p class="small text-muted-soft mb-2">{{ $ownerSide ? 'Renter: '.($inquiry->renter?->name ?? 'Account unavailable') : 'Owner: '.($inquiry->owner?->name ?? 'Account unavailable') }}</p>
                <span class="badge {{ $inquiry->status === 'open' ? 'text-bg-primary' : 'text-bg-secondary' }}" data-inquiry-status>{{ ucfirst($inquiry->status) }}</span>
            </div>
            @if($ownerSide && $inquiry->status === 'open')
                <form method="POST" action="{{ route('owner.inquiries.close', $inquiry) }}" data-confirm="Close this conversation? Neither participant will be able to send more messages.">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-dark" type="submit">Close conversation</button>
                </form>
            @endif
        </header>

        <ol class="inquiry-message-list" aria-label="Conversation messages" aria-live="polite" aria-relevant="additions text" data-inquiry-messages>
            @forelse($inquiry->activities as $activity)
                <li class="inquiry-message {{ $activity->user_id === auth()->id() ? 'is-own' : '' }}" data-activity-id="{{ $activity->id }}">
                    <div class="inquiry-message-bubble">
                        @if($activity->activity_type === 'status_changed')
                            <p class="mb-1">{{ $activity->user?->name ?? 'SpaceMatch' }} updated the inquiry status to {{ $activity->to_status }}.</p>
                        @else
                            <div class="inquiry-message-author">{{ $activity->user?->name ?? 'SpaceMatch' }}</div>
                            <p class="mb-1" style="white-space: pre-wrap">{{ $activity->body }}</p>
                        @endif
                        <time class="small" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('M j, Y g:i A') }}</time>
                    </div>
                </li>
            @empty
                <li class="small text-muted-soft" data-inquiry-empty>No conversation activity.</li>
            @endforelse
        </ol>
        <p class="visually-hidden" role="status" aria-live="polite" data-inquiry-live-status></p>
        @if($inquiry->status === 'open')
            <form class="inquiry-reply-form" method="POST" action="{{ route($routePrefix.'.inquiries.reply', $inquiry) }}" data-inquiry-reply-form>
                @csrf
                <label class="form-label fw-semibold" for="inquiry-reply">Write a message</label>
                <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch">
                    <textarea class="form-control" id="inquiry-reply" name="message" rows="2" maxlength="4000" required aria-describedby="inquiry-message-help">{{ old('message') }}</textarea>
                    <button class="btn btn-primary-custom align-self-sm-end" type="submit">Send message</button>
                </div>
                <div class="form-text" id="inquiry-message-help">Only you and the other conversation participant can see these messages.</div>
            </form>
        @else
            <div class="alert alert-secondary mb-0" role="status">This conversation is closed. Browse available spaces if you need help finding another rental.</div>
            <a class="btn btn-outline-dark mt-3" href="{{ route('properties.index') }}">Browse rentals</a>
        @endif
    </section>
</div>
@endsection
