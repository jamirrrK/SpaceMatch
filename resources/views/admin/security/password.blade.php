@extends('layouts.app')

@section('title', 'Administrator security')

@section('content')
<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <section class="card border-0 shadow-sm p-4">
                <h1 class="h3 mb-2">Secure your administrator account</h1>
                <p class="text-muted">Set a new password before continuing. Use at least 14 characters with uppercase and lowercase letters, a number, and a symbol.</p>
                <form method="POST" action="{{ route('admin.security.password.update') }}" class="vstack gap-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="form-label" for="current_password">Current password</label>
                        <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="password">New password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
                        @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary align-self-start" type="submit">Change password</button>
                </form>
                <hr>
                <h2 class="h6">Multi-factor authentication</h2>
                <p class="small text-muted mb-0">MFA enrollment is not currently available. Do not treat this account as production-ready until an MFA provider and recovery process are configured.</p>
            </section>
        </div>
    </div>
</main>
@endsection
