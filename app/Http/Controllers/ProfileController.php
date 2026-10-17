<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Exceptions\OtpDeliveryFailed;
use App\Exceptions\OtpDeliveryUnavailable;
use App\Services\OtpVerificationService;
use App\Services\PhilippineMobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile', ['user' => Auth::user()]);
    }

    public function update(Request $request, OtpVerificationService $verification)
    {
        $user = $request->user();
        $rawPhone = $request->input('phone_number');
        $normalizedPhone = PhilippineMobileNumber::normalize(is_string($rawPhone) ? $rawPhone : null);
        if ($request->filled('phone_number') && $normalizedPhone === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone_number' => ['Enter a valid Philippine mobile number.'],
            ]);
        }
        $request->merge(['phone_number' => $normalizedPhone]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone_number' => ['nullable', 'regex:/^\+639\d{9}$/', Rule::unique('users', 'phone_number')->ignore($user->id)],
        ]);

        $emailChanged = mb_strtolower($validated['email']) !== mb_strtolower($user->email);
        $phoneChanged = ($validated['phone_number'] ?? null) !== $user->phone_number;

        if ($emailChanged && $phoneChanged) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Change and verify one contact method at a time.'],
            ]);
        }

        if ($emailChanged) {
            try {
                $verification->issue($user, 'email', 'email_change', $validated['email']);
            } catch (OtpDeliveryUnavailable|OtpDeliveryFailed) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['Email verification is unavailable until a real mail delivery service is configured.'],
                ]);
            }

            $user->update(['name' => $validated['name']]);

            return redirect()->route('verification.notice')->with('status', 'Verify the new email address to finish updating your profile.');
        }

        if ($phoneChanged && $validated['phone_number'] && $user->phone_verified_at) {
            try {
                $verification->issue($user, 'sms', 'phone_change', $validated['phone_number']);
            } catch (OtpDeliveryUnavailable|OtpDeliveryFailed) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'phone_number' => ['SMS verification is unavailable until an authorized provider is configured.'],
                ]);
            }

            $user->update(['name' => $validated['name']]);

            return redirect()->route('verification.notice')->with('status', 'Verify the new mobile number to finish updating your profile.');
        }

        $user->update([
            'name' => $validated['name'],
            'phone_number' => $phoneChanged ? ($validated['phone_number'] ?? null) : $user->phone_number,
            'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
        ]);

        return redirect()->route('profile')->with('status', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('profile')->with('status', 'Password updated successfully.');
    }
}
