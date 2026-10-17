@extends('layouts.app')
@section('title', 'Saved spaces | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <div class="eyebrow mb-2">Your shortlist</div>
        <h1 class="page-heading mb-1">Saved spaces</h1>
        <p class="text-muted-soft mb-0">Keep interesting places together while you compare your options.</p>
    </div>
    @if($spaces->isNotEmpty())
        <div class="row g-4">
            @foreach($spaces as $space)
                <div class="col-md-6 col-xl-4">
                    <x-space-card :space="$space" />
                    <form method="POST" action="{{ route('renter.saved-spaces.destroy', $space) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-dark btn-sm w-100" type="submit">Remove from saved spaces</button>
                    </form>
                </div>
            @endforeach
        </div>
        <x-pagination :paginator="$spaces" />
    @else
        <x-empty-state title="No saved spaces yet" message="Save a rental space while browsing to keep it here for later." :href="route('properties.index')" action="Browse rentals" />
    @endif
</div>
@endsection
