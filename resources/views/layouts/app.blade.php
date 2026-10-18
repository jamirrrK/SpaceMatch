<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#172033">
    <title>@yield('title', 'SpaceMatch')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="description" content="Find your space anywhere in the Philippines. Explore available rentals by location, monthly budget, and rental type with SpaceMatch.">
    <meta name="neutral-image" content="{{ asset('assets/images/space-placeholder.svg') }}">
    @stack('styles')
</head>
<body class="spacematch-app">
    @php($management = auth()->check() && !auth()->user()->requiresContactVerification() && (request()->is('owner/*', 'admin/*', 'renter/*') || request()->routeIs('profile', 'bookings.*', 'payments.index', 'maintenance.index', 'reviews.index', 'notifications.index')))
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-xl nav-shell sticky-top {{ $management ? 'workspace-topbar' : '' }}" aria-label="Main navigation">
        <div class="container-fluid site-width px-3 px-lg-4">
            <a class="navbar-brand brand d-inline-flex align-items-center gap-2" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">S</span>SpaceMatch</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="offcanvas-xl offcanvas-end site-navigation-drawer" tabindex="-1" id="siteNav" aria-labelledby="siteNavTitle">
                <div class="offcanvas-header"><h2 class="offcanvas-title panel-title" id="siteNavTitle">Explore SpaceMatch</h2><button class="btn-close" type="button" data-bs-dismiss="offcanvas" data-bs-target="#siteNav" aria-label="Close navigation"></button></div>
                <div class="offcanvas-body">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-2 py-lg-0">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('properties.*', 'spaces.*') ? 'active' : '' }}" href="{{ route('properties.index') }}">Browse rentals</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#rental-categories">Rental categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#how-it-works">How it works</a></li>
                    
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
                            @php($dashboardRoute = auth()->user()->isAdministrator() ? route('admin.dashboard') : route('dashboard'))
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('*.dashboard', 'admin.*') ? 'active' : '' }}" href="{{ $dashboardRoute }}">Dashboard</a></li>
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
        </div>
    </nav>
    <div class="{{ $management ? 'app-shell container-fluid site-width' : '' }}">
        @if($management)
            <aside class="app-sidebar">
                <button class="btn btn-outline-dark w-100 d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#managementNav" aria-expanded="false" aria-controls="managementNav">Management menu</button>
                <div class="offcanvas-lg offcanvas-start workspace-navigation-drawer" tabindex="-1" id="managementNav" aria-labelledby="managementNavTitle">
                    <div class="offcanvas-header"><h2 class="offcanvas-title panel-title" id="managementNavTitle">Your workspace</h2><button class="btn-close" type="button" data-bs-dismiss="offcanvas" data-bs-target="#managementNav" aria-label="Close workspace navigation"></button></div>
                    <div class="offcanvas-body">
                    <div class="sidebar-caption">{{ auth()->user()->isAdministrator() ? 'Administration' : (auth()->user()->hasRole('owner') ? 'Owner workspace' : 'Renter workspace') }}</div>
                    <nav aria-label="Management navigation" class="d-grid gap-1">
                        @if(auth()->user()->hasRole('owner'))
                            <a class="sidebar-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}" href="{{ route('owner.dashboard') }}"><x-icon name="grid" /><span>Overview</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.requests.*') ? 'active' : '' }}" href="{{ route('owner.requests.index') }}"><x-icon name="calendar" /><span>Rental requests</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.inquiries.*') ? 'active' : '' }}" href="{{ route('owner.inquiries.index') }}"><x-icon name="mail" /><span>Messages</span><span class="badge text-bg-primary ms-auto" data-unread-inquiries {{ $unreadInquiryCount < 1 ? 'hidden' : '' }}>{{ $unreadInquiryCount }}</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.viewings.*') ? 'active' : '' }}" href="{{ route('owner.viewings.index') }}"><x-icon name="calendar" /><span>Viewing requests</span>@if($unreadViewingCount > 0)<span class="badge text-bg-primary ms-auto">{{ $unreadViewingCount }}</span>@endif</a>
                            <a class="sidebar-link {{ request()->routeIs('owner.properties.*') ? 'active' : '' }}" href="{{ route('owner.properties.index') }}"><x-icon name="building" /><span>My properties</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.spaces.*') ? 'active' : '' }}" href="{{ route('owner.spaces.index') }}"><x-icon name="home" /><span>Rental spaces</span></a>
                            <a class="sidebar-link {{ request()->routeIs('bookings.index') ? 'active' : '' }}" href="{{ route('bookings.index') }}"><x-icon name="calendar" /><span>Bookings</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.tenants.*') ? 'active' : '' }}" href="{{ route('owner.tenants.index') }}"><x-icon name="user" /><span>Tenants</span></a>
                            <a class="sidebar-link {{ request()->routeIs('payments.index') ? 'active' : '' }}" href="{{ route('payments.index') }}"><x-icon name="wallet" /><span>Rental payments</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.payments.submissions.*') ? 'active' : '' }}" href="{{ route('owner.payments.submissions.index') }}"><x-icon name="wallet" /><span>Payment proof review</span></a>
                            <a class="sidebar-link {{ request()->routeIs('maintenance.index') ? 'active' : '' }}" href="{{ route('maintenance.index') }}"><x-icon name="tool" /><span>Maintenance</span></a>
                            <a class="sidebar-link {{ request()->routeIs('reviews.index') ? 'active' : '' }}" href="{{ route('reviews.index') }}"><x-icon name="star" /><span>Property reviews</span></a>
                            <a class="sidebar-link {{ request()->routeIs('owner.reports.*') ? 'active' : '' }}" href="{{ route('owner.reports.index') }}"><x-icon name="grid" /><span>Reports and analytics</span></a>
                            <a class="sidebar-link" href="{{ route('owner.properties.create') }}"><x-icon name="building" /><span>Add a property</span></a>
                        @elseif(auth()->user()->isAdministrator())
                            <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><x-icon name="grid" /><span>Platform overview</span></a>
                            <a class="sidebar-link {{ request()->routeIs('admin.users.index') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><x-icon name="user" /><span>Users</span></a>
                            <a class="sidebar-link {{ request()->routeIs('admin.properties.*') ? 'active' : '' }}" href="{{ route('admin.properties.index') }}"><x-icon name="building" /><span>All properties</span></a>
                            <a class="sidebar-link {{ request()->routeIs('admin.bookings.index') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}"><x-icon name="calendar" /><span>Bookings</span></a>
                            <a class="sidebar-link {{ request()->routeIs('admin.payments.submissions.*') ? 'active' : '' }}" href="{{ route('admin.payments.submissions.index') }}"><x-icon name="wallet" /><span>Payment proof review</span></a>
                            <a class="sidebar-link" href="{{ route('reviews.index') }}"><x-icon name="star" /><span>Review moderation</span></a>
                            <a class="sidebar-link {{ request()->routeIs('admin.audit-logs.index') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}"><x-icon name="shield" /><span>Audit logs</span></a>
                            <a class="sidebar-link {{ request()->routeIs('admin.security.password.*') ? 'active' : '' }}" href="{{ route('admin.security.password.edit') }}"><x-icon name="shield" /><span>Security center</span></a>
                            @if(auth()->user()->hasRole(\App\Models\User::ROLE_MASTER_ADMIN))
                                <a class="sidebar-link {{ request()->routeIs('admin.master-admins.*') ? 'active' : '' }}" href="{{ route('admin.master-admins.index') }}"><x-icon name="shield" /><span>Administrator access</span></a>
                            @endif
                        @else
                            <a class="sidebar-link {{ request()->routeIs('renter.dashboard') ? 'active' : '' }}" href="{{ route('renter.dashboard') }}"><x-icon name="grid" /><span>Overview</span></a>
                            <a class="sidebar-link {{ request()->routeIs('properties.*', 'spaces.*') ? 'active' : '' }}" href="{{ route('properties.index') }}"><x-icon name="search" /><span>Browse rentals</span></a>
                            <a class="sidebar-link {{ request()->routeIs('renter.saved-spaces.*', 'renter.saved-properties.*') ? 'active' : '' }}" href="{{ route('renter.saved-spaces.index') }}"><x-icon name="home" /><span>Saved spaces</span></a>
                            <a class="sidebar-link {{ request()->routeIs('renter.requests.*') ? 'active' : '' }}" href="{{ route('renter.requests.index') }}"><x-icon name="calendar" /><span>Rental requests</span></a>
                            <a class="sidebar-link {{ request()->routeIs('renter.inquiries.*') ? 'active' : '' }}" href="{{ route('renter.inquiries.index') }}"><x-icon name="mail" /><span>Messages</span><span class="badge text-bg-primary ms-auto" data-unread-inquiries {{ $unreadInquiryCount < 1 ? 'hidden' : '' }}>{{ $unreadInquiryCount }}</span></a>
                            <a class="sidebar-link {{ request()->routeIs('renter.viewings.*') ? 'active' : '' }}" href="{{ route('renter.viewings.index') }}"><x-icon name="calendar" /><span>Viewings</span>@if($unreadViewingCount > 0)<span class="badge text-bg-primary ms-auto">{{ $unreadViewingCount }}</span>@endif</a>
                        @endif
                        <div class="sidebar-group-label">Rental activity</div>
                        @unless(auth()->user()->hasRole('owner'))
                            <a class="sidebar-link {{ request()->routeIs('bookings.index') ? 'active' : '' }}" href="{{ route('bookings.index') }}"><x-icon name="calendar" /><span>Bookings</span></a>
                            <a class="sidebar-link {{ request()->routeIs('payments.index') ? 'active' : '' }}" href="{{ route('payments.index') }}"><x-icon name="wallet" /><span>Payments</span></a>
                        @endunless
                        @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
                        <a class="sidebar-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><x-icon name="bell" /><span>Notifications</span><span class="badge text-bg-light ms-auto" data-unread-notifications {{ $unreadNotifications < 1 ? 'hidden' : '' }}>{{ $unreadNotifications }}</span></a>
                        @unless(auth()->user()->hasRole('owner'))
                            <a class="sidebar-link {{ request()->routeIs('maintenance.index') ? 'active' : '' }}" href="{{ route('maintenance.index') }}"><x-icon name="tool" /><span>Maintenance</span></a>
                        @endunless
                        @if(auth()->user()->hasRole('renter'))
                            <a class="sidebar-link {{ request()->routeIs('reviews.index') ? 'active' : '' }}" href="{{ route('reviews.index') }}"><x-icon name="star" /><span>Reviews</span></a>
                        @endif
                        <div class="sidebar-group-label">Your account</div>
                        <a class="sidebar-link {{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}"><x-icon name="user" /><span>My profile</span></a>
                    </nav>
                    <div class="sidebar-note mt-4">A place for your next chapter.</div>
                    </div>
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
            @if($management)<x-workspace-breadcrumb />@endif
            @yield('content')
        </main>
    </div>
    <footer class="site-footer">
        <div class="container-fluid site-width px-4">
            <div class="footer-grid">
                <div><a class="brand text-white text-decoration-none" href="{{ route('home') }}">SpaceMatch<span class="text-primary">.</span></a><p class="small mt-3 mb-0" style="max-width:340px">Find Your Space, Anywhere in the Philippines.<br>Room for your next chapter.</p></div>
                <div><div class="footer-title">Find your space</div><div class="footer-links"><a href="{{ route('properties.index') }}">Browse rentals</a><a href="{{ route('home') }}#rental-categories">Rental categories</a><a href="{{ route('home') }}#how-it-works">How it works</a></div></div>
                <div><div class="footer-title">Make yourself at home</div><div class="footer-links"><a href="{{ route('home') }}#about">About SpaceMatch</a><a href="{{ route('home') }}#faq">Frequently asked questions</a><a href="{{ route('terms') }}">Terms</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ auth()->check() ? 'Your workspace' : 'Join SpaceMatch' }}</a></div></div>
            </div>
            <div class="footer-meta"><span>&copy; {{ date('Y') }} SpaceMatch. All rights reserved.</span><span>A rental marketplace for the Philippines.</span></div>
        </div>
    </footer>
    <x-confirm-dialog />
    @stack('scripts')
@guest<x-registration-chooser />@endguest
</body>
</html>
