<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function edit(): View
    {
        return view('admin.security.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'password_change_required' => false,
        ])->save();

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'admin.password_changed',
            'description' => json_encode(['source' => 'admin_security'], JSON_THROW_ON_ERROR),
        ]);

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('status', 'Your administrator password has been changed.');
    }
}
