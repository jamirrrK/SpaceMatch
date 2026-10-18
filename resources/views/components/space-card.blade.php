@props(['space', 'management' => false])
@php
    $mediaService = app(\App\Services\PropertyImageService::class);
    $cover = $space->images->concat($space->property->images->whereNull('space_id'))->first(fn ($image) => $mediaService->readableFile($image));
    $types = config('property.types', []);
@endphp
<article class="card-listing">
    <a class="listing-media" href="{{ $management ? route('owner.spaces.edit', $space) : route('properties.show', $space->property).'#space-'.$space->id }}" tabindex="-1" aria-hidden="true"><img class="listing-thumb" src="{{ $cover?->url ?? asset('assets/images/space-placeholder.svg') }}" alt="{{ $cover ? $space->space_name.' photograph' : 'No property photo uploaded' }}" data-safe-image loading="lazy" width="640" height="480"></a>
    <div class="listing-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><span class="small text-muted-soft">{{ $types[$space->space_type] ?? ucfirst(str_replace('_', ' ', $space->space_type ?? 'Rental space')) }}</span><x-status-badge :status="$space->availability_status" /></div>
        <h3 class="listing-title mb-1"><a href="{{ $management ? route('owner.spaces.edit', $space) : route('properties.show', $space->property).'#space-'.$space->id }}">{{ $space->space_name }}</a></h3>
        @include('properties.student-metadata')
        <x-rating :average="$space->property->rating_average" :count="(int)$space->property->rating_count" />
        <p class="listing-property-name mb-1">{{ $space->property->property_name }}</p>
        <p class="listing-location mb-2"><x-icon name="pin" />{{ $space->property->city }}, {{ $space->property->province }}</p>
        <div class="listing-meta mb-3"><span>{{ $space->capacity }} {{ $space->capacity == 1 ? 'person' : 'people' }}</span>@if($space->floor_area)<span>{{ number_format($space->floor_area, 1) }} m²</span>@endif @if($space->floor_number !== null)<span>Floor {{ $space->floor_number }}</span>@endif</div>
        
        @if($space->amenities->isNotEmpty())<div class="d-flex flex-wrap gap-1 mb-3">@foreach($space->amenities->take(4) as $amenity)<span class="amenity-chip">{{ $amenity->name }}</span>@endforeach @if($space->amenities->count() > 4)<span class="amenity-chip">+{{ $space->amenities->count() - 4 }}</span>@endif</div>@endif
        <div class="d-flex justify-content-between align-items-center gap-2 pt-3 border-top"><div class="listing-price">₱{{ number_format($space->price, fmod((float)$space->price, 1) === 0.0 ? 0 : 2) }}<small> / month</small></div><a class="small-link" href="{{ $management ? route('owner.spaces.edit', $space) : route('properties.show', $space->property).'#space-'.$space->id }}">{{ $management ? 'Edit space' : 'View details' }} <span aria-hidden="true">↗</span></a></div>
        @unless($management)<x-space-actions :space="$space" />@endunless
        @if($management)<form class="d-flex justify-content-between align-items-center gap-2 mt-3" method="POST" action="{{ route('owner.spaces.publish', $space) }}" data-confirm="{{ $space->listing_status === 'published' ? 'Unpublish this space? It will be hidden from public search.' : 'Publish this space? It will appear in public search when its property is active and published, and the space is available.' }}">@csrf @method('PATCH')<input type="hidden" name="listing_status" value="{{ $space->listing_status === 'published' ? 'draft' : 'published' }}"><x-status-badge :status="$space->listing_status" /><button type="submit" class="btn btn-outline-dark btn-sm">{{ $space->listing_status === 'published' ? 'Unpublish' : 'Publish' }}</button></form>@endif
    </div>
</article>
