@props(['images', 'name' => 'Rental space', 'id' => 'property-gallery'])
@php($galleryImages = $images->sortBy([['is_primary', 'desc'], ['display_order', 'asc']])->values())
<div data-gallery {{ $attributes }}>
    <img id="{{ $id }}" class="gallery-main" src="{{ $galleryImages->first()?->url ?? asset('assets/images/space-placeholder.svg') }}" alt="{{ $name }}{{ $galleryImages->isEmpty() ? ' — image coming soon' : '' }}" fetchpriority="high">
    @if($galleryImages->count() > 1)<div class="gallery-thumbnails" aria-label="{{ $name }} photos">@foreach($galleryImages as $photo)<button class="gallery-thumbnail" type="button" data-gallery-target="{{ $id }}" data-gallery-src="{{ $photo->url }}" data-gallery-alt="{{ $name }} — photo {{ $loop->iteration }}" aria-label="View photo {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><img src="{{ $photo->url }}" alt="{{ $name }} thumbnail {{ $loop->iteration }}" loading="lazy"></button>@endforeach</div>@endif
</div>
