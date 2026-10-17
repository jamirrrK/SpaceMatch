@props(['space'])
@php
    $comparisonIds = session('space_comparison', []);
    $comparisonIds = is_array($comparisonIds) ? array_map('intval', $comparisonIds) : [];
    $inComparison = in_array($space->id, $comparisonIds, true);
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mt-3">
    @if($space->availability_status === 'available' && auth()->user()?->hasRole('renter') && !auth()->user()->requiresContactVerification())
        @if($space->is_saved ?? false)
            <form method="POST" action="{{ route('renter.saved-spaces.destroy', $space) }}">@csrf @method('DELETE')<button class="btn btn-outline-dark btn-sm" type="submit">Remove saved</button></form>
        @else
            <form method="POST" action="{{ route('renter.saved-spaces.store', $space) }}">@csrf<button class="btn btn-outline-dark btn-sm" type="submit">Save space</button></form>
        @endif
    @endif
    @if($inComparison)
        <form method="POST" action="{{ route('spaces.compare.destroy', $space) }}">@csrf @method('DELETE')<button class="btn btn-outline-dark btn-sm" type="submit">Remove from compare</button></form>
    @else
        <form method="POST" action="{{ route('spaces.compare.store', $space) }}">@csrf<button class="btn btn-outline-dark btn-sm" type="submit" @disabled(count($comparisonIds) >= 3)>Add to compare</button></form>
    @endif
    @if(count($comparisonIds) > 0)
        <a class="small-link" href="{{ route('spaces.compare.index') }}">Compare ({{ count($comparisonIds) }})</a>
    @endif
    @if($space->availability_status === 'available' && auth()->user()?->hasRole('renter') && !auth()->user()->requiresContactVerification())
        <a class="btn btn-primary-custom btn-sm" href="{{ route('properties.show', $space->property_id) }}#inquiry-space-{{ $space->id }}">Ask the owner</a>
    @elseif($space->availability_status === 'available' && !auth()->check())
        <a class="btn btn-outline-dark btn-sm" href="{{ route('login', ['intended' => route('properties.show', $space->property_id).'#inquiry-space-'.$space->id]) }}">Log in to contact owner</a>
    @endif
    @if($space->availability_status === 'available' && auth()->user()?->hasRole('renter') && !auth()->user()->requiresContactVerification())
        <a class="btn btn-outline-dark btn-sm" href="{{ route('properties.show', $space->property_id) }}#viewing-space-{{ $space->id }}">Request a viewing</a>
    @endif
</div>
