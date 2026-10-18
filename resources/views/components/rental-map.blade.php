@props(['property' => null, 'editor' => false])
@php
    $point = $editor && $property?->latitude !== null && $property?->longitude !== null
        ? ['lat' => (float)$property->latitude, 'lng' => (float)$property->longitude, 'precision' => 'Owner location editor']
        : $property?->publicLocation();
    $items = $property && $point ? [['id' => $property->id, 'name' => $property->property_name ?: 'Selected location', 'price' => null, 'location' => $point, 'url' => $property->exists ? route('properties.show', $property) : null]] : [];
@endphp
<section class="rental-map panel p-4 my-4" data-rental-map data-map-endpoint="{{ route('map.listings', request()->query()) }}" data-map-tile-url="{{ config('marketplace.map_tile_url') }}" data-map-tile-attribution="{{ config('marketplace.map_tile_attribution') }}" data-default-lat="{{ config('marketplace.map_default_lat') }}" data-default-lng="{{ config('marketplace.map_default_lng') }}" data-default-zoom="{{ config('marketplace.map_default_zoom') }}" data-property-map="{{ $property ? 'true' : 'false' }}" data-editor="{{ $editor ? 'true' : 'false' }}" data-points="{{ json_encode($items, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) }}">
    <h2 class="panel-title">{{ $editor ? 'Choose a location pin' : 'Explore rentals on the map' }}</h2>
    <p class="small text-muted-soft">{{ $editor ? 'Click the map to select a pin, then confirm it below. You can also enter coordinates manually.' : 'Published spaces with owner-confirmed public locations appear automatically across the Philippines. Approximate pins show an area, not an exact address.' }}</p>
    <p data-map-status role="status">Loading published rental locations around the Philippines.</p>
    <div data-map-canvas class="rental-map-canvas mt-3" aria-label="Rental location map"></div>
    <div data-map-results class="mt-3"></div>
    <a href="{{ route('properties.index') }}" class="small-link">Browse available spaces</a> &middot; <a href="{{ route('privacy') }}">Privacy Notice</a>
</section>
