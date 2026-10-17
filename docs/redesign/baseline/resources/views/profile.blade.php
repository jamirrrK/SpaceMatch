@extends('layouts.app')

@section('title', 'Profile | SpaceMatch')

@section('content')
<div class="container profile-page py-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4">
                <div class="mb-3 text-center">
                    <div class="brand-mark mx-auto mb-3">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <h3 class="fw-bold mb-1">{{ auth()->user()->name }}</h3>
                    <p class="text-muted-soft mb-0">{{ ucfirst(auth()->user()->role) }}</p>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0"><strong>Email:</strong> {{ auth()->user()->email }}</li>
                    <li class="list-group-item px-0"><strong>Role:</strong> {{ ucfirst(auth()->user()->role) }}</li>
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-4 mb-4">
                <h4 class="fw-bold mb-3">Profile details</h4>
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full name</label>
                            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control rounded-3" required>
                            @if($user->email_verified_at)<div class="form-text">Verified email</div>@else<div class="form-text">Email not verified</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Philippine mobile number</label>
                            <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="form-control" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX">
                            <div class="form-text">{{ $user->phone_verified_at ? 'Verified mobile number' : 'Not verified. SMS verification requires a configured provider.' }}</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary-custom mt-3">Save profile</button>
                </form>
            </div>

            <div class="card p-4">
                <h4 class="fw-bold mb-3">Change password</h4>
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="current_password" class="form-label">Current password</label>
                            <div class="input-group"><input id="current_password" type="password" name="current_password" class="form-control" required><button class="btn btn-outline-dark" type="button" data-toggle-password="current_password" aria-label="Show current password" aria-pressed="false">Show</button></div>
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">New password</label>
                            <div class="input-group"><input id="password" type="password" name="password" class="form-control" required><button class="btn btn-outline-dark" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false">Show</button></div>
                        </div>
                        <div class="col-md-4">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <div class="input-group"><input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required><button class="btn btn-outline-dark" type="button" data-toggle-password="password_confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark mt-3">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
