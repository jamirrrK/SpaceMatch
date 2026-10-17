@extends('layouts.app')

@section('title', 'Forgot Password | SpaceMatch')

@section('content')
<div class="container auth-page py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card auth-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3">S</div>
                    <h1 class="fw-bold mb-1">Reset your password</h1>
                    <p class="text-muted-soft mb-0">Enter your email to receive a reset link.</p>
                </div>

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg rounded-3" required>
                    </div>
                    <button type="submit" class="btn btn-primary-custom btn-lg w-100 rounded-3">Send reset link</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
