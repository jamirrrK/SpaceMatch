@extends('layouts.app')
@section('title', $property->property_name.' | SpaceMatch')
@section('content')
<div class="container property-detail-page py-4 py-lg-5">
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ route('properties.index') }}">Browse rentals</a><span aria-hidden="true">/</span>
        <span aria-current="page">Property details</span>
    </nav>

    <header class="d-flex justify-content-between align-items-start flex-wrap gap-3 mt-4 mb-4">
        <div>
            <div class="eyebrow mb-2">{{ config('property.types.'.$property->property_type, 'Rental property') }}</div>
            <h1 class="page-heading mb-2">{{ $property->property_name }}</h1>
            <p class="text-muted-soft mb-0">{{ collect([$property->address, $property->barangay, $property->city, $property->province, $property->postal_code])->filter()->implode(', ') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <x-status-badge :status="$property->status" />
            @auth
                @if(auth()->user()->role === \App\Models\User::ROLE_RENTER)
                    <form method="POST" action="{{ route($isSaved ? 'renter.saved-properties.destroy' : 'renter.saved-properties.store', $property) }}">
                        @csrf
                        @if($isSaved)@method('DELETE')@endif
                        <button class="btn btn-outline-dark" type="submit">{{ $isSaved ? 'Remove saved' : 'Save property' }}</button>
                    </form>
                @endif
            @endauth
        </div>
    </header>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-image-gallery :images="$property->images->whereNull('space_id')" :name="$property->property_name" :id="'property-'.$property->id" />
            <section class="panel p-4 mt-4">
                <h2 class="panel-title mb-3">About this property</h2>
                <p class="detail-description mb-0">{{ $property->description ?: 'Explore the rental spaces below for pricing, capacity, and amenities.' }}</p>
            </section>
        </div>
        <aside class="col-lg-4 property-detail-aside">
            <div class="panel p-4">
                <x-rental-price :spaces="$property->spaces" />
                <h2 class="panel-title mb-4">Property at a glance</h2>
                <dl class="detail-meta mb-4">
                    <div><dt>Property type</dt><dd>{{ config('property.types.'.$property->property_type, 'Rental property') }}</dd></div>
                    <div><dt>Listed spaces</dt><dd>{{ $property->spaces->count() }}</dd></div>
                    <div><dt>City</dt><dd>{{ $property->city }}</dd></div>
                    <div><dt>Province</dt><dd>{{ $property->province }}</dd></div>
                    @if($property->owner)<div><dt>Listed by</dt><dd>{{ $property->owner->name }}</dd></div>@endif
                </dl>
                <a class="btn btn-primary-custom w-100" href="#rental-spaces">Explore rental spaces</a>
                <p class="small text-muted-soft mt-3 mb-0">Prices shown are monthly rental rates. See each space for its amenities and availability.</p>
            </div>
            @php($propertyAmenities = $property->spaces->flatMap->amenities->unique('id'))
            @if($propertyAmenities->isNotEmpty())
                <div class="panel p-4 mt-4">
                    <h2 class="panel-title mb-3">Amenities across this property</h2>
                    <div class="d-flex flex-wrap gap-2">@foreach($propertyAmenities as $amenity)<span class="amenity-chip">{{ $amenity->name }}</span>@endforeach</div>
                    <p class="form-text mb-0 mt-3">Amenities vary by space. Check the details below.</p>
                </div>
            @endif
        </aside>
    </div>

    <section id="rental-spaces" class="pt-5">
        <div class="eyebrow mb-2">Find your fit</div>
        <h2 class="section-title mb-4">Rental spaces</h2>
        <div class="row g-4">
            @forelse($property->spaces as $space)
                <div class="col-12" id="space-{{ $space->id }}">
                    <article class="panel p-3 p-lg-4">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <x-image-gallery :images="$space->images->isNotEmpty() ? $space->images : $property->images->whereNull('space_id')" :name="$space->space_name" :id="'space-gallery-'.$space->id" />
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                    <span class="eyebrow">{{ config('property.types.'.$space->space_type, 'Rental space') }}</span>
                                    <x-status-badge :status="$space->availability_status" />
                                </div>
                                <h3 class="listing-title fs-4 mb-2">{{ $space->space_name }}</h3>
                                <div class="listing-price mb-3">₱{{ number_format($space->price, 2) }}<small> / month</small></div>
                                <div class="listing-meta mb-3">
                                    <span>Capacity: {{ $space->capacity }} {{ $space->capacity == 1 ? 'person' : 'people' }}</span>
                                    @if($space->bedrooms !== null)<span>{{ $space->bedrooms }} {{ \Illuminate\Support\Str::plural('bedroom', $space->bedrooms) }}</span>@endif
                                    @if($space->bathrooms !== null)<span>{{ $space->bathrooms }} {{ \Illuminate\Support\Str::plural('bathroom', $space->bathrooms) }}</span>@endif
                                    @if($space->floor_area)<span>Floor area: {{ number_format($space->floor_area, 1) }} m²</span>@endif
                                    @if($space->floor_number !== null)<span>Floor {{ $space->floor_number }}</span>@endif
                                    @if($space->available_from)<span>Available from {{ $space->available_from->format('M j, Y') }}</span>@endif
                                </div>
                                @if($space->description)<p class="detail-description">{{ $space->description }}</p>@endif
                                @if($space->amenities->isNotEmpty())
                                    <h4 class="small fw-semibold mt-3">Included amenities</h4>
                                    <div class="d-flex flex-wrap gap-2">@foreach($space->amenities as $amenity)<span class="amenity-chip">{{ $amenity->name }}</span>@endforeach</div>
                                @endif
                                @if($space->deposit_amount !== null || $space->advance_months !== null || $space->house_rules || $space->move_in_requirements || $space->cancellation_policy)
                                    <details class="mt-3">
                                        <summary class="fw-semibold">Rental terms</summary>
                                        <dl class="detail-meta mt-3 mb-0">
                                            @if($space->deposit_amount !== null)<div><dt>Security deposit</dt><dd>₱{{ number_format((float) $space->deposit_amount, 2) }}</dd></div>@endif
                                            @if($space->advance_months !== null)<div><dt>Advance rent</dt><dd>{{ $space->advance_months }} {{ \Illuminate\Support\Str::plural('month', $space->advance_months) }}</dd></div>@endif
                                            @if($space->house_rules)<div><dt>House rules</dt><dd>{{ $space->house_rules }}</dd></div>@endif
                                            @if($space->move_in_requirements)<div><dt>Move-in requirements</dt><dd>{{ $space->move_in_requirements }}</dd></div>@endif
                                            @if($space->cancellation_policy)<div><dt>Cancellation policy</dt><dd>{{ $space->cancellation_policy }}</dd></div>@endif
                                        </dl>
                                    </details>
                                @endif

                                @auth
                                    @if(auth()->user()->role === \App\Models\User::ROLE_RENTER && $space->availability_status === 'available')
                                        <form class="panel p-3 mt-4" method="POST" action="{{ route('renter.requests.store', $space) }}">
                                            @csrf
                                            <h4 class="panel-title mb-3">Request this space</h4>
                                            <div class="row g-3">
                                                <div class="col-sm-6"><label class="form-label" for="move-in-{{ $space->id }}">Move-in date</label><input class="form-control" id="move-in-{{ $space->id }}" type="date" name="move_in_date" min="{{ now()->toDateString() }}" value="{{ old('move_in_date') }}" required></div>
                                                <div class="col-sm-6"><label class="form-label" for="occupants-{{ $space->id }}">Occupants (max {{ $space->capacity }})</label><input class="form-control" id="occupants-{{ $space->id }}" type="number" name="occupants" min="1" max="{{ $space->capacity }}" value="{{ old('occupants', 1) }}" required></div>
                                                <div class="col-sm-6">
                                                    <label class="form-label" for="duration-{{ $space->id }}">Rental duration</label>
                                                    <select class="form-select" id="duration-{{ $space->id }}" name="duration_months" required>
                                                        @foreach([1, 3, 6, 12, 24, 36] as $months)<option value="{{ $months }}" @selected(old('duration_months', 6) == $months)>{{ $months }} {{ \Illuminate\Support\Str::plural('month', $months) }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div class="col-sm-6"><label class="form-label" for="contact-{{ $space->id }}">Contact phone</label><input class="form-control" id="contact-{{ $space->id }}" type="tel" name="contact_phone" maxlength="30" value="{{ old('contact_phone', auth()->user()->phone_number) }}" required></div>
                                                <div class="col-12"><label class="form-label" for="message-{{ $space->id }}">Message to the owner (optional)</label><textarea class="form-control" id="message-{{ $space->id }}" name="message" rows="3" maxlength="2000">{{ old('message') }}</textarea></div>
                                            </div>
                                            <button class="btn btn-primary-custom mt-3" type="submit">Send rental request</button>
                                            <p class="small text-muted-soft mt-2 mb-0">This sends a request for the owner to review. It does not process payment or guarantee availability.</p>
                                        </form>
                                    @endif
                                @else
                                    @if($space->availability_status === 'available')
                                        <a class="btn btn-primary-custom mt-4" href="{{ route('login') }}">Sign in to request this space</a>
                                    @endif
                                @endauth
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><x-empty-state title="No published spaces available" message="There are no rental spaces to show for this property at the moment." :href="route('properties.index')" action="Explore other spaces" /></div>
            @endforelse
        </div>
    </section>

    <section class="panel p-4 mt-5" aria-labelledby="property-reviews-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="panel-title mb-0" id="property-reviews-title">Renter reviews</h2>
            @if($rating['count'] > 0)<p class="mb-0"><strong>{{ number_format((float) $rating['average'], 1) }}/5</strong> · {{ $rating['count'] }} {{ \Illuminate\Support\Str::plural('review', $rating['count']) }}</p>@endif
        </div>
        @forelse($reviews as $review)
            <article class="border-top py-3">
                <div class="d-flex justify-content-between gap-2"><strong>{{ $review->booking->renter->name }}</strong><span class="text-warning fw-semibold">{{ $review->rating }}/5</span></div>
                <p class="small text-muted-soft mb-1">{{ $review->booking->space->space_name }} · {{ $review->created_at->format('M j, Y') }}</p>
                @if($review->comment)<p class="mb-0">{{ $review->comment }}</p>@endif
            </article>
        @empty
            <p class="text-muted-soft mb-0">No completed-rental reviews have been submitted for this property.</p>
        @endforelse
    </section>
</div>
@endsection
