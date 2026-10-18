@extends('layouts.app')
@section('title', 'Inquiries | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">{{ $ownerSide ? 'Owner workspace' : 'Your rental search' }}</div>
        <h1 class="page-heading mb-1">Inquiries</h1>
        <p class="small text-muted-soft mb-0">{{ $ownerSide ? 'Reply to students asking about your spaces.' : 'Follow your conversations with property owners.' }}</p>
    </div>
    @if($inquiries->isNotEmpty())
        <div class="d-grid gap-3">
            @foreach($inquiries as $inquiry)
                <article class="panel p-4">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <div class="eyebrow mb-1">{{ $inquiry->space?->property?->property_name ?? 'Property unavailable' }}</div>
                            <h2 class="panel-title mb-1">{{ $inquiry->space?->space_name ?? 'Space unavailable' }}</h2>
                            <p class="small text-muted-soft mb-2">{{ $ownerSide ? 'Student: '.($inquiry->renter?->name ?? 'Account unavailable') : 'Owner: '.($inquiry->owner?->name ?? 'Account unavailable') }}</p>
                            <span class="badge {{ $inquiry->status === 'open' ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ ucfirst($inquiry->status) }}</span>
                            @if($inquiry->unread_activities_count > 0)<span class="badge text-bg-warning">{{ $inquiry->unread_activities_count }} unread</span>@endif
                        </div>
                        <a class="btn btn-outline-dark" href="{{ route($ownerSide ? 'owner.inquiries.show' : 'renter.inquiries.show', $inquiry) }}">Open conversation</a>
                    </div>
                </article>
            @endforeach
        </div>
        <x-pagination :paginator="$inquiries" />
    @else
        <x-empty-state
            title="{{ $ownerSide ? 'No student inquiries yet' : 'No inquiries yet' }}"
            message="{{ $ownerSide ? 'When a student asks about one of your published spaces, the conversation will appear here.' : 'Ask a property owner about a space to start a private conversation.' }}"
            :href="$ownerSide ? route('owner.properties.index') : route('properties.index')"
            :action="$ownerSide ? 'View my listings' : 'Browse rentals'"
        />
    @endif
</div>
@endsection
