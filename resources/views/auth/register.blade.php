@extends('layouts.app')

@section('title', ($studentMode ?? false) ? 'Student registration | SpaceMatch' : 'Register | SpaceMatch')

@section('content')
@php
    $registrationMode = $registrationMode ?? 'renter';
    $registrationRoute = $registrationMode === 'owner' ? 'owner.register' : ($registrationMode === 'student' ? 'student.register' : 'renter.register');
    $emailEnabled = in_array('email', $verificationChannels ?? [], true);
    $smsEnabled = in_array('sms', $verificationChannels ?? [], true);
    $selectedMethod = old('verification_method', $emailEnabled ? 'email' : ($smsEnabled ? 'sms' : ''));
@endphp
<div class="container auth-page py-5">
    <div class="row justify-content-center">
        <x-auth-story />
        <div class="col-lg-7">
            <div class="card auth-card p-4 p-lg-5">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3">S</div>
                    <h1 class="fw-bold mb-1">{{ $registrationMode === 'student' ? 'Find your student home' : ($registrationMode === 'owner' ? 'Register as a Property Owner' : 'Register as a Renter') }}</h1>
                    <p class="text-muted-soft mb-0">{{ ($studentMode ?? false) ? 'School, budget, and your next chapter?all in one place.' : 'Join SpaceMatch as a renter or property owner' }}</p>
                </div>

                <form method="POST" action="{{ route($registrationRoute) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="name" class="form-label">Full name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control form-control-lg rounded-3" required>
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label">Email address <span class="required-mark">*</span></label>
                            <input id="email" type="email" autocomplete="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg rounded-3" required>
                        </div>

                        <div class="col-12">
                            <label for="phone_number" class="form-label">Philippine mobile number <span class="text-muted-soft fw-normal">(optional)</span></label>
                            <input id="phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}" class="form-control form-control-lg" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX">
                            <div class="form-text">Required only when SMS verification is available and selected.</div>
                        </div>

                        <div class="col-12">
                            <fieldset class="verification-methods">
                                <legend class="form-label">How would you like to verify your SpaceMatch account?</legend>
                                <label class="verification-method {{ $emailEnabled ? '' : 'is-disabled' }}">
                                    <input type="radio" name="verification_method" value="email" @checked($selectedMethod === 'email') @disabled(! $emailEnabled)>
                                    <span><strong>Send code to my email</strong><small>{{ $emailEnabled ? 'A one-time code will be sent to your email address.' : 'Email delivery is not configured for this app.' }}</small></span>
                                </label>
                                <label class="verification-method {{ $smsEnabled ? '' : 'is-disabled' }}">
                                    <input type="radio" name="verification_method" value="sms" @checked($selectedMethod === 'sms') @disabled(! $smsEnabled)>
                                    <span><strong>Send code to my mobile number</strong><small>{{ $smsEnabled ? 'A one-time code will be sent by SMS.' : 'SMS verification is unavailable until an authorized provider is configured.' }}</small></span>
                                </label>
                                @error('verification_method')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </fieldset>
                            @if(! $emailEnabled && ! $smsEnabled)
                                <div class="alert alert-warning mt-3 mb-0" role="status">Account verification delivery is not configured yet. Registration is temporarily unavailable; no account will be created without a deliverable code.</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group"><input id="password" type="password" autocomplete="new-password" name="password" class="form-control form-control-lg" required><button class="btn btn-outline-dark" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false">Show</button></div>
                        </div>

                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <div class="input-group"><input id="password_confirmation" type="password" autocomplete="new-password" name="password_confirmation" class="form-control form-control-lg" required><button class="btn btn-outline-dark" type="button" data-toggle-password="password_confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button></div>
                        </div>

                        <input type="hidden" name="account_type" value="{{ $registrationMode === 'owner' ? 'owner' : 'renter' }}">
                        @if($studentMode ?? false)
                            @include('renter.preferences.student-registration-fields')
                            <div class="col-md-6"><label class="form-label" for="budget_min">Minimum monthly budget (optional)</label><input class="form-control" type="number" min="0" max="9999999999" name="budget_min" id="budget_min" value="{{ old('budget_min') }}"></div>
                            <div class="col-md-6"><label class="form-label" for="budget_max">Maximum monthly budget (optional)</label><input class="form-control" type="number" min="0" max="9999999999" name="budget_max" id="budget_max" value="{{ old('budget_max') }}"></div>
                        @else
                            <div class="col-12"><label class="form-label" for="preferred_location">{{ $registrationMode === 'owner' ? 'Preferred operating location' : 'Preferred location (optional)' }}</label><input class="form-control" name="preferred_location" id="preferred_location" maxlength="150" value="{{ old('preferred_location') }}"></div>
                        @endif
                        @if($registrationMode === 'owner')
                            <div class="col-md-6"><label class="form-label" for="owner_type">Owner type</label><select class="form-select" id="owner_type" name="owner_type" required><option value="individual" @selected(old('owner_type') === 'individual')>Individual</option><option value="organization" @selected(old('owner_type') === 'organization')>Organization</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="organization_name">Business / organization name</label><input class="form-control" name="organization_name" id="organization_name" maxlength="180" value="{{ old('organization_name') }}"><p class="form-text">Required for organizations. No identity document is requested.</p></div>
                        @endif
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="terms" id="terms" value="1" required {{ old('terms') ? 'checked' : '' }}>
                                <label class="form-check-label" for="terms">I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms of Service</a> ({{ config('marketplace.terms_version') }}).</label>
                            </div>
                        </div>
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="privacy_acknowledgment" id="privacy_acknowledgment" value="1" required @checked(old('privacy_acknowledgment'))><label class="form-check-label" for="privacy_acknowledgment">I acknowledge the <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Notice</a>.</label></div></div>
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="adult_confirmation" id="adult_confirmation" value="1" required @checked(old('adult_confirmation'))><label class="form-check-label" for="adult_confirmation">I confirm I am 18 or older. Accounts for minors are not supported.</label></div></div>
                        <div class="col-12"><p class="small text-muted-soft">Terms and Privacy are review drafts. No marketing consent is requested or enabled.</p><a href="{{ route('register') }}" data-registration-choice>Choose a different account type</a></div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom btn-lg w-100 rounded-3 mt-4" @disabled(! $emailEnabled && ! $smsEnabled)>Create account</button>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted-soft">Already have an account? <a href="{{ route('login') }}" class="fw-semibold text-decoration-none">Log in</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
