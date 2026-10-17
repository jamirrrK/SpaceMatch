@extends('layouts.app')
@section('title', 'Compare spaces | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <div class="eyebrow mb-2">Make an informed choice</div>
        <h1 class="page-heading mb-1">Compare rental spaces</h1>
        <p class="text-muted-soft mb-0">Compare up to three currently available listings. Prices shown are monthly.</p>
    </div>
    @if($spaces->isNotEmpty())
        <div class="table-wrap panel" role="region" tabindex="0" aria-label="Space comparison table">
            <table class="table mb-0">
                <thead><tr><th scope="col">Details</th>@foreach($spaces as $space)<th scope="col">{{ $space->space_name }}</th>@endforeach</tr></thead>
                <tbody>
                    <tr><th scope="row">Property</th>@foreach($spaces as $space)<td>{{ $space->property->property_name }}</td>@endforeach</tr>
                    <tr><th scope="row">Location</th>@foreach($spaces as $space)<td>{{ $space->property->city }}, {{ $space->property->province }}</td>@endforeach</tr>
                    <tr><th scope="row">Type</th>@foreach($spaces as $space)<td>{{ config('property.types.'.$space->space_type, ucfirst(str_replace('_', ' ', $space->space_type))) }}</td>@endforeach</tr>
                    <tr><th scope="row">Monthly rent</th>@foreach($spaces as $space)<td>₱{{ number_format((float) $space->price, 2) }}</td>@endforeach</tr>
                    <tr><th scope="row">Capacity</th>@foreach($spaces as $space)<td>{{ $space->capacity }} {{ $space->capacity === 1 ? 'person' : 'people' }}</td>@endforeach</tr>
                    <tr><th scope="row">Floor area</th>@foreach($spaces as $space)<td>{{ $space->floor_area ? number_format((float) $space->floor_area, 1).' m²' : 'Not provided' }}</td>@endforeach</tr>
                    <tr><th scope="row">Amenities</th>@foreach($spaces as $space)<td>{{ $space->amenities->pluck('name')->join(', ') ?: 'Not provided' }}</td>@endforeach</tr>
                    <tr><th scope="row">Availability</th>@foreach($spaces as $space)<td>{{ ucfirst(str_replace('_', ' ', $space->availability_status)) }}</td>@endforeach</tr>
                    <tr><th scope="row">Actions</th>@foreach($spaces as $space)<td>
                        <a class="small-link d-block mb-2" href="{{ route('properties.show', $space->property_id) }}#space-{{ $space->id }}">View listing</a>
                        <form method="POST" action="{{ route('spaces.compare.destroy', $space) }}">@csrf @method('DELETE')<button class="btn btn-outline-dark btn-sm" type="submit">Remove</button></form>
                    </td>@endforeach</tr>
                </tbody>
            </table>
        </div>
    @else
        <x-empty-state title="Nothing to compare yet" message="Add spaces from search results or property details to compare rent, capacity, location, and amenities." :href="route('properties.index')" action="Browse rentals" />
    @endif
    <a href="{{ route('properties.index') }}" class="btn btn-primary-custom mt-4">Browse more rentals</a>
</div>
@endsection
