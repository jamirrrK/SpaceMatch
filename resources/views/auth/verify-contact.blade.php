@extends('layouts.app')

@section('title', 'Verify your account | SpaceMatch')

@section('content')
<div class="container auth-page py-5">
    <div class="row justify-content-center">
        <x-auth-story />
        <div class="col-lg-6 col-xl-5">
            <section class="card auth-card otp-card p-4 p-lg-5" aria-labelledby="verification-title">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3" aria-hidden="true">S</div>
                    <div class="eyebrow mb-2">One more step</div>
                    <h1 class="fw-bold mb-2" id="verification-title">Verify Your SpaceMatch Account</h1>
                    <p class="text-muted-soft mb-0">{{ $challengeSent ? "We've sent a 6-digit verification code to your selected contact method." : 'Your account is waiting for contact verification. Request a code when delivery is available.' }}</p>
                </div>

                <div class="otp-destination" aria-live="polite">
                    <span class="otp-destination-icon" aria-hidden="true">{{ $channel === 'sms' ? 'SMS' : '@' }}</span>
                    <span><small>{{ $challengeSent ? ($channel === 'sms' ? 'Text message sent to' : 'Email sent to') : ($channel === 'sms' ? 'Mobile number to verify' : 'Email address to verify') }}</small><strong>{{ $destination }}</strong></span>
                </div>

                <form method="POST" action="{{ route('verification.verify') }}" class="mt-4">
                    @csrf
                    <label for="otp-code" class="form-label">6-digit code</label>
                    <input id="otp-code" class="form-control otp-code-input @error('code') is-invalid @enderror" type="text" name="code" value="{{ old('code') }}" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" minlength="6" required autofocus data-otp-input aria-describedby="otp-expiry-note">
                    @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    <p class="form-text mt-2" id="otp-expiry-note" data-otp-expires-at="{{ $expiresAt?->timestamp ?? '' }}" aria-live="polite">@if($challengeSent)Your code expires in 5 minutes.@elseRequest a code when the resend option becomes available.@endif</p>
                    <button class="btn btn-primary-custom btn-lg w-100 mt-3" type="submit" data-otp-submit>Verify code</button>
                </form>

                <div class="otp-resend-row mt-4">
                    <span class="small text-muted-soft">Didn't receive a code?</span>
                    <form method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button class="btn btn-outline-dark btn-sm" type="submit" data-otp-submit data-otp-resend-at="{{ $sentAt?->timestamp ?? '' }}" @disabled(! ($channel === 'sms' ? $smsAvailable : $emailAvailable))>Resend code</button>
                    </form>
                </div>
                @unless($channel === 'sms' ? $smsAvailable : $emailAvailable)
                    <p class="small text-danger mt-3 mb-0" role="alert">Code delivery is currently unavailable. Please try again later.</p>
                @endunless

                <p class="small text-muted-soft mt-4 mb-0">You can browse public listings while verification is pending.</p>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3">
                    <a class="small-link" href="{{ route('properties.index') }}">Browse spaces</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link btn-sm p-0 text-decoration-none" type="submit">Log out</button></form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection