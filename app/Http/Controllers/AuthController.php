<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Exceptions\OtpDeliveryFailed;
use App\Exceptions\OtpDeliveryUnavailable;
use App\Services\OtpVerificationService;
use App\Services\PhilippineMobileNumber;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user?->requiresContactVerification()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended($this->redirectForRole($user));
    }

    public function showRegistrationForm(OtpVerificationService $verification)
    {
        return view('auth.register', [
            'verificationChannels' => $verification->availableChannels(),
        ]);
    }

    public function register(Request $request, OtpVerificationService $verification)
    {
        $rawPhone = $request->input('phone_number');
        $normalizedPhone = PhilippineMobileNumber::normalize(is_string($rawPhone) ? $rawPhone : null);
        if ($request->filled('phone_number') && $normalizedPhone === null) {
            throw ValidationException::withMessages([
                'phone_number' => ['Enter a valid Philippine mobile number such as 09XXXXXXXXX or +639XXXXXXXXX.'],
            ]);
        }
        $request->merge(['phone_number' => $normalizedPhone]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'account_type' => ['required', 'in:renter,owner'],
            'verification_method' => ['required', Rule::in($verification->availableChannels())],
            'phone_number' => [Rule::requiredIf($request->input('verification_method') === 'sms'), 'nullable', 'regex:/^\+639\d{9}$/', 'unique:users,phone_number'],
            'terms' => ['accepted'],
        ], [
            'account_type.in' => 'Please choose a valid account type.',
            'verification_method.in' => 'Choose a verification method that is currently available.',
            'phone_number.required' => 'Enter a Philippine mobile number to verify by SMS.',
            'phone_number.regex' => 'Enter a valid Philippine mobile number.',
        ]);

        $role = $validated['account_type'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $role,
            'password' => Hash::make($validated['password']),
            'phone_number' => $validated['phone_number'] ?? null,
            'verification_method' => $validated['verification_method'],
            'verification_required_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        try {
            $verification->issue($user, $validated['verification_method']);
        } catch (OtpDeliveryUnavailable|OtpDeliveryFailed) {
            return redirect()->route('verification.notice')
                ->withErrors(['code' => 'We could not send your verification code. Please try again.']);
        }

        return redirect()->route('verification.notice')
            ->with('status', 'Your verification code has been sent.');
    }

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetPasswordForm(string $token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    protected function redirectForRole(?User $user): string
    {
        if (! $user) {
            return route('home');
        }

        return match ($user->role) {
            'admin' => route('admin.dashboard'),
            'owner' => route('owner.dashboard'),
            'renter' => route('renter.dashboard'),
            default => route('dashboard'),
        };
    }
}
