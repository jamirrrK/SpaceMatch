@props(['space', 'management' => false])
@php
    $cover = $space->images->sortByDesc('is_primary')->first() ?? $space->property->images->whereNull('space_id')->sortByDesc('is_primary')->first();
    $types = config('property.types', []);
@endphp
<article class="card-listing">
    <img class="listing-thumb" src="{{ $cover?->url ?? asset('assets/images/space-placeholder.svg') }}" alt="{{ $space->space_name }}" loading="lazy">
    <div class="p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><span class="small text-muted-soft">{{ $types[$space->space_type] ?? ucfirst(str_replace('_', ' ', $space->space_type ?? 'Rental space')) }}</span><x-status-badge :status="$space->availability_status" /></div>
        <h3 class="listing-title mb-1">{{ $space->space_name }}</h3>
        <p class="listing-location mb-2">{{ $space->property->property_name }} · {{ $space->property->city }}</p>
        <div class="listing-meta mb-3"><span>{{ $space->capacity }} {{ $space->capacity == 1 ? 'person' : 'people' }}</span>@if($space->floor_area)<span>{{ number_format($space->floor_area, 1) }} m²</span>@endif @if($space->floor_number !== null)<span>Floor {{ $space->floor_number }}</span>@endif</div>
        @if($space->description)<p class="small text-muted-soft text-truncate-2">{{ $space->description }}</p>@endif
        @if($space->amenities->isNotEmpty())<div class="d-flex flex-wrap gap-1 mb-3">@foreach($space->amenities->take(4) as $amenity)<span class="amenity-chip">{{ $amenity->name }}</span>@endforeach @if($space->amenities->count() > 4)<span class="amenity-chip">+{{ $space->amenities->count() - 4 }}</span>@endif</div>@endif
        <div class="d-flex justify-content-between align-items-center gap-2 pt-3 border-top"><div class="listing-price">₱{{ number_format($space->price, 2) }}<small> / month</small></div><a class="small-link" href="{{ $management ? route('owner.spaces.edit', $space) : route('properties.show', $space->property).'#space-'.$space->id }}">{{ $management ? 'Edit space' : 'View details' }} <span aria-hidden="true">↗</span></a></div>
        @if($management)<form class="d-flex justify-content-between align-items-center gap-2 mt-3" method="POST" action="{{ route('owner.spaces.publish', $space) }}" data-confirm="{{ $space->listing_status === 'published' ? 'Unpublish this space? It will be hidden from public search.' : 'Publish this space? It will appear in public search when its property is active and published, and the space is available.' }}">@csrf @method('PATCH')<input type="hidden" name="listing_status" value="{{ $space->listing_status === 'published' ? 'draft' : 'published' }}"><x-status-badge :status="$space->listing_status" /><button type="submit" class="btn btn-outline-dark btn-sm">{{ $space->listing_status === 'published' ? 'Unpublish' : 'Publish' }}</button></form>@endif
    </div>
</article>
