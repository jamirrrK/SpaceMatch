@extends('layouts.app')
@section('title', 'Find your next space | SpaceMatch')
@section('content')
@php
    $featuredSpaces = $featuredSpaces ?? collect();
    $heroSpace = $featuredSpaces->first();
    $heroImage = $heroSpace?->images?->first() ?? $heroSpace?->property?->images?->whereNull('space_id')->first();
@endphp
<section class="home-hero">
    <div class="container site-width">
        <div class="row align-items-center g-4 home-hero-grid">
            <div class="col-lg-6">
                <div class="home-hero-copy">
                    <div class="hero-kicker"><span class="hero-kicker-mark" aria-hidden="true">S</span> A little closer to home</div>
                    <h1>Find a place that<br>feels like <em>yours.</em></h1>
                    <p class="home-hero-description">Discover bedspaces, dormitories, apartments, and more across the Philippines. A new city. A fresh start. A space for you.</p>
                    <div class="home-hero-notes" aria-label="SpaceMatch highlights">
                        <span>Monthly prices</span>
                        <span>Detailed listings</span>
                        <span>Owner-managed spaces</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                @if($heroSpace)
                    <a class="home-hero-visual home-hero-feature" href="{{ route('properties.show', $heroSpace->property).'#space-'.$heroSpace->id }}">
                        <img src="{{ $heroImage?->url ?? asset('assets/images/space-placeholder.svg') }}" alt="{{ $heroSpace->space_name }} at {{ $heroSpace->property->property_name }}" fetchpriority="high">
                        <span class="hero-image-label">Featured available space</span>
                        <span class="home-hero-caption">
                            <span class="eyebrow">{{ $heroSpace->property->city }}, {{ $heroSpace->property->province }}</span>
                            <strong>{{ $heroSpace->property->property_name }}</strong>
                            <span>{{ $heroSpace->space_name }} <b>&#8369;{{ number_format($heroSpace->price, 0) }} / month</b></span>
                        </span>
                    </a>
                @else
                    <div class="home-hero-visual home-hero-illustration">
                        <img src="{{ asset('assets/images/space-placeholder.svg') }}" alt="Illustration of a welcoming rental space">
                        <div class="hero-image-overlays" aria-hidden="true">
                            <span class="hero-image-badge">Room for what matters</span>
                        </div>
                        <span class="home-hero-caption">
                            <span class="eyebrow">Explore at your pace</span>
                            <strong>Your next chapter starts with a space that feels right.</strong>
                        </span>
                    </div>
                @endif
            </div>
        </div>
        <div class="home-search-wrap"><div class="search-introduction"><x-icon name="search" /><span>Where will your next chapter begin?</span></div>
                    <form action="{{ route('properties.index') }}" method="GET" class="home-search">
                        <div class="home-search-field home-search-location">
                            <label for="home-location">Location</label>
                            <input class="form-control" id="home-location" type="search" name="location" maxlength="150" placeholder="City, neighborhood, or address">
                        </div>
                        <div class="home-search-field">
                            <label for="home-property-type">Property type</label>
                            <select class="form-select" id="home-property-type" name="property_type">
                                <option value="">Any type</option>
                                @foreach(config('property.types', []) as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="home-search-field"><label for="home-max-price">Monthly budget (PHP)</label><input class="form-control" id="home-max-price" type="number" name="max_price" min="0" max="9999999999.99" step="0.01" placeholder="Maximum rent"></div>
                        <button class="btn btn-primary-custom home-search-submit" type="submit"><x-icon name="search" /> Search spaces</button>
                    </form>
        </div>
    </div>
</section>

<section class="home-section home-category-section" id="rental-categories">
    <div class="container site-width">
        <div class="home-section-heading">
            <div><div class="eyebrow mb-2">Explore by category</div><h2 class="section-title mb-0">Find a rental type that fits.</h2></div>
            <a class="small-link" href="{{ route('properties.index') }}">Browse every listing <span aria-hidden="true">&#8594;</span></a>
        </div>
        <nav class="category-rail" aria-label="Rental categories">
            @foreach(config('property.types', []) as $value => $label)
                <div><a class="rental-category" href="{{ route('properties.index', ['property_type' => $value]) }}"><span class="category-icon"><x-icon name="building" /></span><span>{{ $label }}</span><span class="rental-category-arrow" aria-hidden="true">&#8594;</span></a></div>
            @endforeach
        </nav>
    </div>
</section>

<section class="home-section" id="featured-spaces">
    <div class="container site-width">
        <div class="home-section-heading">
            <div><div class="eyebrow mb-2">Published and available</div><h2 class="section-title mb-0">Spaces to explore</h2></div>
            <a class="small-link" href="{{ route('properties.index') }}">Browse all spaces <span aria-hidden="true">&#8594;</span></a>
        </div>
        <div class="row g-4">
            @forelse($featuredSpaces as $space)
                <div class="col-md-6 col-lg-4"><x-space-card :space="$space" /></div>
            @empty
                <div class="col-12"><x-empty-state title="No featured spaces right now" message="Browse current listings to see what is available, or check back as owners publish more spaces." :href="route('properties.index')" action="Browse available spaces" /></div>
            @endforelse
        </div>
    </div>
</section>

<section class="home-section home-how-section" id="how-it-works">
    <div class="container site-width">
        <div class="home-section-intro"><div class="eyebrow mb-2">A straightforward search</div><h2 class="section-title">Your next move, made clearer.</h2><p class="text-muted-soft mb-0">Explore real listings and make your next move with the details in view.</p></div>
        <div class="row g-3 home-step-grid">
            <div class="col-md-4"><article class="home-step"><span class="home-step-number">01</span><h3>Search your way</h3><p>Start with a location, then filter by monthly budget, property type, amenities, or capacity.</p></article></div>
            <div class="col-md-4"><article class="home-step"><span class="home-step-number">02</span><h3>Compare the details</h3><p>Review photos, rental spaces, listed amenities, capacity, and availability on each property page.</p></article></div>
            <div class="col-md-4"><article class="home-step"><span class="home-step-number">03</span><h3>Manage your space</h3><p>Owners can organize property details, add spaces and photos, and publish listings when ready.</p></article></div>
        </div>
    </div>
</section>

<section class="home-benefits-section" id="about">
    <div class="container site-width">
        <div class="row g-4">
            <div class="col-lg-5"><div class="eyebrow mb-2">About SpaceMatch</div><h2 class="section-title">Different chapters.
One place to begin.</h2><p class="text-muted-soft mb-0">SpaceMatch brings rental listings and property management into one clear experience. Browse public listings without an account, or sign in to manage the tools available to your role.</p></div>
            <div class="col-lg-7"><div class="row g-3">
                <div class="col-md-6"><article class="home-benefit"><span class="home-benefit-mark" aria-hidden="true">01</span><h3>For renters</h3><p>Search published rentals, compare monthly prices and amenities, and keep your account details up to date.</p><a href="{{ route('properties.index') }}">Explore spaces <span aria-hidden="true">&#8594;</span></a></article></div>
                <div class="col-md-6"><article class="home-benefit home-benefit-owner"><span class="home-benefit-mark" aria-hidden="true">02</span><h3>For property owners</h3><p>Create properties, manage rental spaces and photos, and publish listings for renters to discover.</p><a href="{{ auth()->check() && auth()->user()->hasRole('owner') ? route('owner.dashboard') : route('register') }}">{{ auth()->check() && auth()->user()->hasRole('owner') ? 'Open owner workspace' : 'Create an owner account' }} <span aria-hidden="true">&#8594;</span></a></article></div>
            </div></div>
        </div>
    </div>
</section>

<section class="home-trust-section">
    <div class="container site-width d-flex flex-wrap align-items-start justify-content-between gap-4">
        <div><div class="eyebrow mb-2">Clear and transparent</div><h2 class="section-title mb-2">Know what each listing represents.</h2></div>
        <div class="home-trust-copy"><p class="mb-0">Public search shows properties that are active and published, with spaces marked published and available. SpaceMatch does not guarantee every listing; review its photos, price, location, and details carefully before making arrangements.</p><a class="small-link" href="{{ route('properties.index') }}">Review available listings <span aria-hidden="true">&#8594;</span></a></div>
    </div>
</section>

<section class="home-section home-faq-section" id="faq">
    <div class="container site-width">
        <div class="row g-4">
            <div class="col-lg-4"><div class="eyebrow mb-2">Good to know</div><h2 class="section-title">Frequently asked questions</h2><p class="text-muted-soft">A few details before you begin.</p></div>
            <div class="col-lg-8">
                <details class="home-faq-item"><summary>Can I browse listings without an account?</summary><p>Yes. Published and available spaces can be searched and viewed without signing in.</p></details>
                <details class="home-faq-item"><summary>How are rental prices shown?</summary><p>Space prices are displayed as monthly rates on search results and property details.</p></details>
                <details class="home-faq-item"><summary>How do owners publish a property?</summary><p>Owners create a property, add at least one rental space, then publish the space and property from the owner workspace.</p></details>
                <details class="home-faq-item"><summary>Which account types can I create?</summary><p>Public registration is available for renters and property owners. Administrator accounts are managed separately.</p></details>
            </div>
        </div>
    </div>
</section>

<section class="home-closing-band">
    <div class="container site-width d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div><div class="eyebrow mb-2">For property owners</div><h2 class="section-title mb-2">Your space. Someone’s next chapter.</h2><p class="mb-0">Give your property a place to be discovered. Manage spaces, photos, and listing details.</p></div>
        <a class="btn btn-primary-custom" href="{{ auth()->check() && auth()->user()->hasRole('owner') ? route('owner.properties.create') : route('register') }}">List your property <span aria-hidden="true">&#8594;</span></a>
    </div>
</section>
@endsection
