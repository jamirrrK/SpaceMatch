@extends('layouts.app')
@section('title', 'Notifications | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 my-4">
        <div><div class="eyebrow mb-2">Updates</div><h1 class="page-heading mb-1">Notifications</h1><p class="text-muted-soft mb-0">Rental request, booking, and payment updates for your account.</p></div>
        @if(auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-outline-dark" type="submit">Mark all as read</button></form>
        @endif
    </div>
    <div class="d-grid gap-3">
        @forelse($notifications as $notification)
            <article class="panel p-4 {{ $notification->read_at ? '' : 'border-start border-4 border-primary' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $notification->data['title'] ?? 'SpaceMatch update' }}</h2>
                        <p class="mb-2">{{ $notification->data['message'] ?? '' }}</p>
                        <time class="small text-muted-soft" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('M j, Y g:i A') }}</time>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @if(!empty($notification->data['url']))<a class="btn btn-outline-dark btn-sm" href="{{ $notification->data['url'] }}">View details</a>@endif
                        @unless($notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-outline-dark btn-sm" type="submit">Mark read</button></form>@endunless
                    </div>
                </div>
            </article>
        @empty
            <x-empty-state title="You’re all caught up" message="Updates about your rental activity will appear here." />
        @endforelse
    </div>
    <x-pagination :paginator="$notifications" />
</div>
@endsection
