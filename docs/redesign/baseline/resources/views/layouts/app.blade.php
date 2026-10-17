<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#122644">
    <title>@yield('title', 'SpaceMatch')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/spacematch.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/spacematch-stage2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/spacematch-tutorial.css') }}">
    @stack('styles')
</head>
<body>
    @php($management = request()->is('owner/*', 'admin/*', 'renter/*'))
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-xl nav-shell sticky-top" aria-label="Main navigation">
        <div class="container-fluid site-width px-3 px-lg-4">
            <a class="navbar-brand brand d-inline-flex align-items-center gap-2" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">S</span>SpaceMatch</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-2 py-lg-0">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('properties.*', 'spaces.*') ? 'active' : '' }}" href="{{ route('properties.index') }}">Browse rentals</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#rental-categories">Rental categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#how-it-works">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#about">About</a></li>
                    <li class="nav-item"><button class="nav-link tutorial-nav-link" type="button" data-tutorial-replay>Watch tutorial</button></li>
                    @guest
                        <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">List your property</a></li>
                    @else
                        @if(auth()->user()->hasRole('owner'))
                            <li class="nav-item"><a class="nav-link" href="{{ route('owner.properties.create') }}">List your property</a></li>
                        @endif
                    @endguest
                    @auth
                        @if(auth()->user()->requiresContactVerification())
                            <li class="nav-item"><a class="nav-link active" href="{{ route('verification.notice') }}">Verify account</a></li>
                            <li class="nav-item"><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark btn-sm ms-lg-2" type="submit">Log out</button></form></li>
                        @else
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('*.dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="nav-item dropdown account-menu">
                                <button class="btn account-menu-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="account-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                    <span class="account-menu-name">{{ auth()->user()->name }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><span class="dropdown-header">{{ ucfirst(auth()->user()->role) }} account</span></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}">Account settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Log out</button></form></li>
                                </ul>
                            </li>
                        @endif
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Log in</a></li>
                        <li class="nav-item"><a class="btn btn-primary-custom btn-sm px-3 ms-lg-2" href="{{ route('register') }}">Create account</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>
    <div class="{{ $management ? 'app-shell container-fluid site-width' : '' }}">
        @if($management)
            <aside class="app-sidebar">
                <button class="btn btn-outline-dark w-100 d-lg-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#managementNav" aria-expanded="false" aria-controls="managementNav">Management menu</button>
                <div class="collapse d-lg-block" id="managementNav">
                    <div class="sidebar-caption">{{ auth()->user()->hasRole('admin') ? 'Administration' : (auth()->user()->hasRole('owner') ? 'Owner workspace' : 'Renter workspace') }}</div>
                    <nav aria-label="Management navigation" class="d-grid gap-1">
                        @if(auth()->user()->hasRole('owner'))
                            <a class="sidebar-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}" href="{{ route('owner.dashboard') }}">Overview</a>
                            <a class="sidebar-link {{ request()->routeIs('owner.properties.*') ? 'active' : '' }}" href="{{ route('owner.properties.index') }}">My properties</a>
                            <a class="sidebar-link {{ request()->routeIs('owner.spaces.*') ? 'active' : '' }}" href="{{ route('owner.spaces.index') }}">Rental spaces</a>
                            <a class="sidebar-link" href="{{ route('owner.properties.create') }}">Add a property</a>
                        @elseif(auth()->user()->hasRole('admin'))
                            <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Platform overview</a>
                            <a class="sidebar-link {{ request()->routeIs('admin.properties.*') ? 'active' : '' }}" href="{{ route('admin.properties.index') }}">All properties</a>
                        @else
                            <a class="sidebar-link {{ request()->routeIs('renter.dashboard') ? 'active' : '' }}" href="{{ route('renter.dashboard') }}">Overview</a>
                            <a class="sidebar-link {{ request()->routeIs('properties.*', 'spaces.*') ? 'active' : '' }}" href="{{ route('properties.index') }}">Browse properties</a>
                        @endif
                        <a class="sidebar-link" href="{{ route('bookings.index') }}">Bookings</a>
                        <a class="sidebar-link" href="{{ route('payments.index') }}">Payments</a>
                        <a class="sidebar-link" href="{{ route('maintenance.index') }}">Maintenance</a>
                        @if(auth()->user()->hasRole('renter'))
                            <a class="sidebar-link {{ request()->routeIs('reviews.index') ? 'active' : '' }}" href="{{ route('reviews.index') }}">Reviews</a>
                        @endif
                        <a class="sidebar-link" href="{{ route('profile') }}">My profile</a>
                    </nav>
                    <div class="sidebar-note mt-4">A little more space.<br>A lot more possibility.</div>
                </div>
            </aside>
        @endif
        <main id="main-content" class="{{ $management ? 'app-main' : '' }}" tabindex="-1">
            @if(session('status') || session('success') || session('error') || $errors->any())
                <div class="{{ $management ? '' : 'container' }} pt-4">
                    @if(session('status') || session('success'))<div class="alert alert-success" role="status">{{ session('success') ?? session('status') }}</div>@endif
                    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
                    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <footer class="site-footer mt-5 py-4">
        <div class="container-fluid site-width px-4 d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><a class="fw-bold text-white text-decoration-none" href="{{ route('home') }}">SpaceMatch</a><div class="small mt-1 text-white-50">Find your place. Manage it with confidence.</div></div><a class="small text-white-50" href="{{ route('properties.index') }}">Explore rental spaces</a></div>
    </footer>
    <x-confirm-dialog />
    <x-tutorial-modal />
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/spacematch.js') }}" defer></script>
    <script src="{{ asset('assets/js/spacematch-tutorial.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
