<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'role' => $request->input('role'),
            'verification' => $request->input('verification'),
        ];

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone_number', 'like', $term);
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if ($filters['verification'] === 'pending') {
            $query->whereNotNull('verification_required_at');
        } elseif ($filters['verification'] === 'verified') {
            $query->whereNull('verification_required_at');
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users', 'filters'));
    }
}
