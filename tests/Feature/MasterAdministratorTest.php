<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_administrator_routes_are_separate_and_admin_role_changes_are_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $master = User::factory()->create(['role' => User::ROLE_MASTER_ADMIN]);
        $target = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.master-admins.index'))->assertForbidden();
        $this->actingAs($master)->get(route('admin.master-admins.index'))->assertOk()->assertSee($target->email);

        $this->patch(route('admin.master-admins.update', $target), [
            'role' => User::ROLE_MASTER_ADMIN,
        ])->assertRedirect();
        $this->assertSame(User::ROLE_MASTER_ADMIN, $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $master->id,
            'action' => 'master_admin.role_changed',
        ]);
    }

    public function test_master_role_has_administrator_access_and_cannot_be_granted_to_unverified_accounts(): void
    {
        $master = User::factory()->create(['role' => User::ROLE_MASTER_ADMIN]);
        $unverified = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'verification_required_at' => now(),
        ]);

        $this->actingAs($master)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->patch(route('admin.master-admins.update', $unverified), [
            'role' => User::ROLE_MASTER_ADMIN,
        ])->assertSessionHasErrors('role');
        $this->assertSame(User::ROLE_ADMIN, $unverified->fresh()->role);

        $property = Property::factory()->published()->create();
        $this->assertTrue($master->can('view', $property));
    }

    public function test_artisan_provisioning_only_promotes_a_confirmed_existing_administrator_and_is_audited(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'existing-admin@example.test',
        ]);

        $this->artisan('spacematch:provision-master-admin existing-admin@example.test')
            ->expectsConfirmation('Grant master administrator access to existing-admin@example.test?', 'yes')
            ->expectsOutput('Master administrator access granted to existing-admin@example.test.')
            ->assertSuccessful();

        $this->assertSame(User::ROLE_MASTER_ADMIN, $admin->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master_admin.provisioned',
            'description' => json_encode(['target_user_id' => $admin->id, 'source' => 'artisan']),
        ]);
    }

    public function test_artisan_provisioning_decline_and_non_admin_targets_leave_roles_unchanged(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'declined@example.test']);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER, 'email' => 'renter@example.test']);

        $this->artisan('spacematch:provision-master-admin declined@example.test')
            ->expectsConfirmation('Grant master administrator access to declined@example.test?', 'no')
            ->assertSuccessful();
        $this->artisan('spacematch:provision-master-admin renter@example.test')
            ->expectsOutput('Only an existing administrator account can be promoted.')
            ->assertExitCode(1);

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
        $this->assertSame(User::ROLE_RENTER, $renter->fresh()->role);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
