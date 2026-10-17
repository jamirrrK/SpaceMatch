@extends('layouts.app')

@section('title', 'Reset Password | SpaceMatch')

@section('content')
<div class="container auth-page py-5">
    <div class="row justify-content-center">
        <x-auth-story />
        <div class="col-lg-5">
            <div class="card auth-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3">S</div>
                    <h1 class="fw-bold mb-1">Set a new password</h1>
                    <p class="text-muted-soft mb-0">Choose a strong new password.</p>
                </div>

                <form method="POST" action="{{ route('password.store') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" type="email" autocomplete="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <div class="input-group"><input id="password" type="password" autocomplete="new-password" name="password" class="form-control form-control-lg" required><button class="btn btn-outline-dark" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false">Show</button></div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <div class="input-group"><input id="password_confirmation" type="password" autocomplete="new-password" name="password_confirmation" class="form-control form-control-lg" required><button class="btn btn-outline-dark" type="button" data-toggle-password="password_confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button></div>
                    </div>
                    <button type="submit" class="btn btn-primary-custom btn-lg w-100 rounded-3">Reset password</button>
                </form>
                <a class="small-link mt-4" href="{{ route('login') }}">&larr; Back to log in</a>
            </div>
        </div>
    </div>
</div>
@endsection
