@extends('layouts.app')

@section('title', 'Login | SpaceMatch')

@section('content')
<div class="container auth-page py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card auth-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3">S</div>
                    <h1 class="fw-bold mb-1">Welcome back</h1>
                    <p class="text-muted-soft mb-0">Sign in to continue to SpaceMatch</p>
                </div>

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg rounded-3" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group input-group-lg">
                            <input id="password" type="password" name="password" class="form-control rounded-3" required>
                            <button class="btn btn-outline-dark" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="small text-decoration-none">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary-custom btn-lg w-100 rounded-3">Log in</button>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted-soft">Need an account? <a href="{{ route('register') }}" class="fw-semibold text-decoration-none">Register now</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
