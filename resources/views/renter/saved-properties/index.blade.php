@extends('layouts.app')
@section('title', 'Saved properties | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 my-4">
        <div><div class="eyebrow mb-2">Your shortlist</div><h1 class="page-heading mb-1">Saved properties</h1><p class="text-muted-soft mb-0">Listings you saved for a closer look.</p></div>
        <a class="btn btn-primary-custom" href="{{ route('properties.index') }}">Explore rentals</a>
    </div>
    <div class="row g-4">
        @forelse($properties as $property)
            <div class="col-md-6 col-xl-4">
                <article class="panel h-100 overflow-hidden">
                    <a href="{{ route('properties.show', $property) }}" aria-label="View {{ $property->property_name }}">
                        <img class="listing-thumb w-100" src="{{ $property->images->first()?->url ?? asset('assets/images/space-placeholder.svg') }}" alt="" width="640" height="360" loading="lazy">
                    </a>
                    <div class="p-4">
                        <div class="eyebrow mb-2">{{ config('property.types.'.$property->property_type, 'Rental property') }}</div>
                        <h2 class="listing-title fs-5"><a href="{{ route('properties.show', $property) }}">{{ $property->property_name }}</a></h2>
                        <p class="listing-location">{{ collect([$property->city, $property->province])->filter()->implode(', ') }}</p>
                        @php($availableSpaces = $property->spaces->where('availability_status', 'available')->where('listing_status', 'published'))
                        @if($availableSpaces->isNotEmpty())
                            <p class="small text-muted-soft">{{ $availableSpaces->count() }} available {{ \Illuminate\Support\Str::plural('space', $availableSpaces->count()) }} · from ₱{{ number_format((float) $availableSpaces->min('price'), 2) }}/month</p>
                        @else
                            <p class="small text-muted-soft">No spaces are currently available. The saved listing remains here for reference.</p>
                        @endif
                        <div class="d-flex gap-2">
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('properties.show', $property) }}">View property</a>
                            <form method="POST" action="{{ route('renter.saved-properties.destroy', $property) }}">@csrf @method('DELETE')<button class="btn btn-outline-dark btn-sm" type="submit">Remove</button></form>
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><x-empty-state title="No saved properties yet" message="Save a property from its detail page and it will appear here." :href="route('properties.index')" action="Explore rentals" /></div>
        @endforelse
    </div>
    <x-pagination :paginator="$properties" />
</div>
@endsection
