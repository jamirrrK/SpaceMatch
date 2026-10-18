<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpDeliveryFailed;
use App\Exceptions\OtpDeliveryUnavailable;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function show(Request $request, OtpVerificationService $verification): View|RedirectResponse
    {
        $user = $request->user();
        $purpose = $user->requiresContactVerification() ? 'registration' : null;
        $challenge = $verification->activeChallenge($user, $purpose);

        if (! $challenge && ! $user->requiresContactVerification()) {
            return redirect()->route('dashboard');
        }

        $channel = $challenge?->verification_type ?? $user->verification_method ?? 'email';
        $destination = $challenge?->destination_encrypted
            ?? ($channel === 'sms' ? $user->phone_number : $user->email);

        return view('auth.verify-contact', [
            'channel' => $channel,
            'destination' => $verification->maskDestination($channel, (string) $destination),
            'challengeSent' => $challenge !== null,
            'expiresAt' => $challenge?->expires_at,
            'sentAt' => $challenge?->sent_at,
            'emailAvailable' => $verification->emailAvailable(),
            'smsAvailable' => $verification->smsAvailable(),
        ]);
    }

    public function verify(Request $request, OtpVerificationService $verification): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $wasPendingRegistration = $request->user()->requiresContactVerification();
        $result = $verification->verify($request->user(), $validated['code']);
        if ($result !== 'verified') {
            $message = match ($result) {
                'expired' => 'That code has expired. Request a new code to continue.',
                'locked' => 'Too many incorrect codes. Request a new code to continue.',
                'destination_taken' => 'That contact is already associated with another account. Request a new code for a different contact.',
                default => 'That code is not valid. Check it and try again.',
            };

            throw ValidationException::withMessages(['code' => $message]);
        }

        if (! $wasPendingRegistration) {
            return redirect()->route('profile')->with('status', 'Your new contact has been verified.');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->dashboardFor($request->user()->role))
            ->with('status', 'Your SpaceMatch account is verified.');
    }

    public function resend(Request $request, OtpVerificationService $verification): RedirectResponse
    {
        $user = $request->user();
        $current = $verification->activeChallenge($user);
        $latest = OtpVerification::query()->where('user_id', $user->id)->latest('id')->first();
        $pendingContactChange = $latest && match ($latest->purpose) {
            'email_change' => mb_strtolower($latest->destination_encrypted) !== mb_strtolower($user->email),
            'phone_change' => $latest->destination_encrypted !== $user->phone_number,
            default => false,
        };
        $purpose = $current?->purpose
            ?? ($user->requiresContactVerification() ? 'registration' : ($pendingContactChange ? $latest->purpose : null));
        if (! $purpose) {
            return redirect()->route('dashboard');
        }

        $latest = OtpVerification::query()->where('user_id', $user->id)->where('purpose', $purpose)->latest('id')->first();

        if ($latest && $latest->sent_at->addSeconds((int) config('otp.resend_cooldown_seconds', 60))->isFuture()) {
            $wait = now()->diffInSeconds($latest->sent_at->addSeconds((int) config('otp.resend_cooldown_seconds', 60)));

            return back()->withErrors(['code' => "Please wait {$wait} seconds before requesting another code."]);
        }

        $destination = $current?->destination_encrypted
            ?? $latest?->destination_encrypted
            ?? ($user->verification_method === 'sms' ? $user->phone_number : $user->email);
        $destinationKey = hash_hmac('sha256', (string) $destination, (string) config('app.key'));
        $userRateKey = 'otp-resend-user:'.$user->id.':'.$destinationKey;
        $ipRateKey = 'otp-resend-ip:'.hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));

        if (RateLimiter::tooManyAttempts($userRateKey, (int) config('otp.max_resends_per_hour', 5))
            || RateLimiter::tooManyAttempts($ipRateKey, (int) config('otp.max_resends_per_ip_per_hour', 10))) {
            return back()->withErrors(['code' => 'Too many code requests. Please try again later.']);
        }

        $channel = $current?->verification_type ?? $latest?->verification_type ?? $user->verification_method ?? 'email';

        try {
            $verification->issue($user, $channel, $purpose, (string) $destination);
        } catch (OtpDeliveryUnavailable|OtpDeliveryFailed) {
            return back()->withErrors(['code' => 'We could not send a new code. Please try again later.']);
        }

        RateLimiter::hit($userRateKey, 3600);
        RateLimiter::hit($ipRateKey, 3600);

        return back()->with('status', 'A new verification code has been sent.');
    }

    private function dashboardFor(string $role): string
    {
        return match ($role) {
            User::ROLE_ADMIN, User::ROLE_MASTER_ADMIN => route('admin.dashboard'),
            'owner' => route('owner.dashboard'),
            'renter' => route('renter.dashboard'),
            default => route('dashboard'),
        };
    }
}