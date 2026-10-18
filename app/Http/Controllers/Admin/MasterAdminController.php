<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterAdminController extends Controller
{
    public function index(): View
    {
        $administrators = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MASTER_ADMIN])
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.master-admins.index', compact('administrators'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_MASTER_ADMIN])],
        ]);

        DB::transaction(function () use ($request, $user, $data): void {
            User::query()->where('role', User::ROLE_MASTER_ADMIN)->orderBy('id')->lockForUpdate()->get(['id']);
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! in_array($target->role, [User::ROLE_ADMIN, User::ROLE_MASTER_ADMIN], true)) {
                throw ValidationException::withMessages(['role' => 'Only existing administrator accounts can be changed here.']);
            }

            if ($target->role === $data['role']) {
                return;
            }

            if ($target->id === $request->user()->id && $data['role'] === User::ROLE_ADMIN) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own master administrator access.']);
            }

            if ($data['role'] === User::ROLE_MASTER_ADMIN && $target->requiresContactVerification()) {
                throw ValidationException::withMessages(['role' => 'Verify the administrator account contact information before granting master access.']);
            }

            if ($target->role === User::ROLE_MASTER_ADMIN
                && User::query()->where('role', User::ROLE_MASTER_ADMIN)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one master administrator must remain.']);
            }

            $previousRole = $target->role;
            $target->update(['role' => $data['role']]);
            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'master_admin.role_changed',
                'description' => json_encode([
                    'target_user_id' => $target->id,
                    'from' => $previousRole,
                    'to' => $data['role'],
                ], JSON_THROW_ON_ERROR),
            ]);
        });

        return back()->with('status', 'Administrator access was updated and recorded in the audit log.');
    }
}
