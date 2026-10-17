<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_booking_and_audit_workspaces(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        foreach (['admin.users.index', 'admin.bookings.index', 'admin.audit-logs.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_renters_and_owners_cannot_access_admin_workspaces(): void
    {
        foreach ([User::ROLE_RENTER, User::ROLE_OWNER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach (['admin.users.index', 'admin.bookings.index', 'admin.audit-logs.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
    }
}
