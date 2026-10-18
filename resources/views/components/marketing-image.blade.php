@props(['name', 'alt', 'sizes' => '100vw', 'priority' => false])
@php
    $image = config('marketing.images.'.$name);
    $variants = $image['variants'];
    $fallback = $variants[count($variants) - 1];
@endphp
<picture {{ $attributes->class(['marketing-picture']) }}>
    <source type="image/webp" srcset="{{ collect($variants)->map(fn ($variant) => asset($variant['webp']).' '.$variant['width'].'w')->implode(', ') }}" sizes="{{ $sizes }}">
    <img src="{{ asset($fallback['jpg']) }}" srcset="{{ collect($variants)->map(fn ($variant) => asset($variant['jpg']).' '.$variant['width'].'w')->implode(', ') }}" sizes="{{ $sizes }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="{{ $alt }}" loading="{{ $priority ? 'eager' : 'lazy' }}" @if($priority) fetchpriority="high" @endif decoding="async">
</picture>
