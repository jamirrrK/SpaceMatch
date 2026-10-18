@extends('layouts.app')
@section('title', 'Messages | SpaceMatch')
@section('content')
<div class="inquiry-workspace">
    <header class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <div class="eyebrow mb-2">{{ $ownerSide ? 'Owner workspace' : 'Your rental search' }}</div>
            <h1 class="page-heading mb-1">Messages</h1>
            <p class="small text-muted-soft mb-0">{{ $ownerSide ? 'Talk with renters interested in your spaces.' : 'Keep your rental conversations in one place.' }}</p>
        </div>
        <label class="visually-hidden" for="inquiry-search">Search messages</label>
        <input class="form-control inquiry-search" id="inquiry-search" type="search" placeholder="Search spaces or people" data-inquiry-search>
    </header>
    @if($inquiries->isNotEmpty())
        <section class="panel inquiry-list" aria-label="Conversations">
            @foreach($inquiries as $inquiry)
                @php
                    $participant = $ownerSide ? $inquiry->renter : $inquiry->owner;
                    $image = $inquiry->space?->images?->first();
                @endphp
                <a class="inquiry-list-item" href="{{ route($ownerSide ? 'owner.inquiries.show' : 'renter.inquiries.show', $inquiry) }}"
                    data-inquiry-search-item="{{ strtolower(($inquiry->space?->property?->property_name ?? '').' '.($inquiry->space?->space_name ?? '').' '.($participant?->name ?? '')) }}">
                    @if($image)
                        <img class="inquiry-list-image" src="{{ $image->url }}" alt="" loading="lazy">
                    @else
                        <span class="inquiry-list-image inquiry-list-placeholder" aria-hidden="true"><x-icon name="home" /></span>
                    @endif
                    <span class="inquiry-list-copy">
                        <span class="d-flex justify-content-between gap-2">
                            <strong>{{ $participant?->name ?? 'Account unavailable' }}</strong>
                            <time class="small text-muted-soft flex-shrink-0" datetime="{{ $inquiry->updated_at->toIso8601String() }}">{{ $inquiry->updated_at->diffForHumans() }}</time>
                        </span>
                        <span class="small text-muted-soft">{{ $inquiry->space?->property?->property_name ?? 'Property unavailable' }} · {{ $inquiry->space?->space_name ?? 'Space unavailable' }}</span>
                        <span class="inquiry-list-preview">{{ $inquiry->latestActivity?->body ?: 'Inquiry status updated.' }}</span>
                    </span>
                    <span class="d-flex flex-column align-items-end gap-2">
                        <span class="badge {{ $inquiry->status === 'open' ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ ucfirst($inquiry->status) }}</span>
                        @if($inquiry->unread_activities_count > 0)<span class="badge text-bg-warning">{{ $inquiry->unread_activities_count }} unread</span>@endif
                    </span>
                </a>
            @endforeach
        </section>
        <div class="mt-4"><x-pagination :paginator="$inquiries" /></div>
        <p class="small text-muted-soft mt-2" data-inquiry-search-empty hidden>No conversations match your search.</p>
    @else
        <x-empty-state
            title="{{ $ownerSide ? 'No student messages yet' : 'No messages yet' }}"
            message="{{ $ownerSide ? 'When a renter asks about one of your published spaces, the conversation will appear here.' : 'Ask an owner about a space to start a private conversation.' }}"
            :href="$ownerSide ? route('owner.properties.index') : route('properties.index')"
            :action="$ownerSide ? 'View my listings' : 'Browse rentals'"
        />
    @endif
</div>
@endsection
