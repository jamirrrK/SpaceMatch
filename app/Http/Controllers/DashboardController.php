<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        return match ($user->role) {
            User::ROLE_ADMIN, User::ROLE_MASTER_ADMIN => redirect()->route('admin.dashboard'),
            'owner' => redirect()->route('owner.dashboard'),
            'renter' => redirect()->route('renter.dashboard'),
            default => redirect()->route('home'),
        };
    }
}
