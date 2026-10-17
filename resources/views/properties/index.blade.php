@extends('layouts.app')
@section('title', 'Explore rental spaces | SpaceMatch')
@section('content')
<section class="marketplace-hero"><div class="container"><div class="eyebrow mb-2">Discover your next chapter</div><h1 class="page-heading mb-2">A space that fits your life.</h1><p class="text-muted-soft mb-0">Explore available rentals by location, budget, and the little things that matter.</p></div></section>
<div class="container py-4 py-lg-5"><div class="row g-4"><aside class="col-lg-3"><div class="filter-sticky"><x-filter-form :amenities="$amenities" :filters="$filters" /></div></aside><section class="col-lg-9" aria-label="Search results"><div class="d-flex justify-content-between align-items-center gap-2 mb-4"><h2 class="panel-title mb-0">{{ number_format($spaces->total()) }} {{ $spaces->total() === 1 ? 'space' : 'spaces' }} available</h2><span class="small text-muted-soft">Monthly rental prices</span></div>@php
    $activeFilters = collect($filters)->except(['page', 'sort'])->filter(fn ($value) => is_array($value) ? count($value) > 0 : $value !== null && $value !== '');
@endphp
        @if($activeFilters->isNotEmpty())<nav class="active-filters" aria-label="Active search filters">
        @foreach($activeFilters as $key => $value)
            @php
                $label = match($key) {
                    'property_type' => config('property.types.'.$value, $value),
                    'min_price' => 'From PHP '.number_format((float)$value),
                    'max_price' => 'Up to PHP '.number_format((float)$value),
                    'capacity' => $value.'+ people',
                    'bedrooms' => $value.'+ bedrooms',
                    'bathrooms' => $value.'+ bathrooms',
                    'available_from' => 'Available by '.\Illuminate\Support\Carbon::parse($value)->format('M j, Y'),
                    'amenities' => count((array)$value).' amenities',
                    default => $value,
                };
            @endphp
            <a class="filter-chip" href="{{ route('properties.index', collect($filters)->except([$key, 'page'])->all()) }}" aria-label="Remove filter: {{ $label }}">{{ $label }} <span aria-hidden="true">&times;</span></a>
        @endforeach
        </nav>@endif
        <div class="row g-4">@forelse($spaces as $space)<div class="col-md-6 col-xl-4"><x-space-card :space="$space" /></div>@empty<div class="col-12"><x-empty-state title="No spaces found" message="Try a different city, a wider budget, or fewer amenities to find your next space." :href="route('properties.index')" action="Clear filters" /></div>@endforelse</div><x-pagination :paginator="$spaces" /></section></div></div>
@endsection
