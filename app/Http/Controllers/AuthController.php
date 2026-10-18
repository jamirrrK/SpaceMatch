<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpDeliveryFailed;
use App\Exceptions\OtpDeliveryUnavailable;
use App\Models\User;
use App\Services\OtpVerificationService;
use App\Services\PhilippineMobileNumber;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function showRegistrationForm(Request $request, OtpVerificationService $verification)
    {
        return view('auth.register', [
            'studentMode' => $request->routeIs('student.register'),
            'registrationMode' => $request->routeIs('owner.register') ? 'owner' : ($request->routeIs('student.register') ? 'student' : 'renter'),
            'verificationChannels' => $verification->availableChannels(),
        ]);
    }

    public function register(Request $request, OtpVerificationService $verification)
    {
        $student = $request->is('register/student');
        if ($student) {
            $request->merge(['account_type' => User::ROLE_RENTER]);
        }
        if ($request->is('register/owner')) $request->merge(['account_type' => User::ROLE_OWNER]);
        if ($request->is('register/renter')) $request->merge(['account_type' => User::ROLE_RENTER]);
        $rawPhone = $request->input('phone_number');
        $normalizedPhone = PhilippineMobileNumber::normalize(is_string($rawPhone) ? $rawPhone : null);
        if ($request->filled('phone_number') && $normalizedPhone === null) {
            throw ValidationException::withMessages([
                'phone_number' => ['Enter a valid Philippine mobile number such as 09XXXXXXXXX or +639XXXXXXXXX.'],
            ]);
        }
        $request->merge(['phone_number' => $normalizedPhone]);

        $validated = $request->validate([
            'school_or_campus' => [Rule::requiredIf($student), 'nullable', 'string', 'max:150'],
            'campus' => ['nullable', 'string', 'max:150'],
            'preferred_location' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'account_type' => ['required', 'in:renter,owner'],
            'verification_method' => ['required', Rule::in($verification->availableChannels())],
            'phone_number' => [Rule::requiredIf($request->input('verification_method') === 'sms'), 'nullable', 'regex:/^\+639\d{9}$/', 'unique:users,phone_number'],
            'terms' => ['accepted'],
            'privacy_acknowledgment' => ['accepted'],
            'adult_confirmation' => ['accepted'],
            'owner_type' => [Rule::requiredIf($request->is('register/owner')), 'nullable', Rule::in(['individual', 'organization'])],
            'organization_name' => ['required_if:owner_type,organization', 'nullable', 'string', 'max:180'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999', Rule::when($request->filled('budget_min'), 'gte:budget_min')],
        ], [
            'account_type.in' => 'Please choose a valid account type.',
            'verification_method.in' => 'Choose a verification method that is currently available.',
            'phone_number.required' => 'Enter a Philippine mobile number to verify by SMS.',
            'phone_number.regex' => 'Enter a valid Philippine mobile number.',
        ]);

        $role = $validated['account_type'];

        $user = DB::transaction(function () use ($validated, $role, $student): User {
            $user = User::create([
                'is_student' => $student,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $role,
                'password' => Hash::make($validated['password']),
                'phone_number' => $validated['phone_number'] ?? null,
                'verification_method' => $validated['verification_method'],
                'verification_required_at' => now(),
            ]);

            $user->forceFill([
                'terms_version' => config('marketplace.terms_version'), 'terms_accepted_at' => now(),
                'privacy_version' => config('marketplace.privacy_version'), 'privacy_acknowledged_at' => now(),
                'owner_type' => $role === User::ROLE_OWNER ? ($validated['owner_type'] ?? 'individual') : null,
                'organization_name' => $role === User::ROLE_OWNER && ($validated['owner_type'] ?? '') === 'organization' ? ($validated['organization_name'] ?? null) : null,
                'preferred_location' => $validated['preferred_location'] ?? null,
            ])->save();

            if ($student) {
                $user->renterPreference()->create(collect($validated)->only(['school_or_campus', 'campus', 'preferred_location', 'budget_min', 'budget_max'])->all());
            }

            return $user;
        });

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
            User::ROLE_ADMIN, User::ROLE_MASTER_ADMIN => route('admin.dashboard'),
            'owner' => route('owner.dashboard'),
            'renter' => route('renter.dashboard'),
            default => route('dashboard'),
        };
    }
}
