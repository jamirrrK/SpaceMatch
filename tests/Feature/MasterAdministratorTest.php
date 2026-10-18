<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $emailUnverified = User::factory()->unverified()->create([
            'role' => User::ROLE_ADMIN,
            'verification_required_at' => null,
        ]);

        $this->actingAs($master)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->patch(route('admin.master-admins.update', $unverified), [
            'role' => User::ROLE_MASTER_ADMIN,
        ])->assertSessionHasErrors('role');
        $this->patch(route('admin.master-admins.update', $emailUnverified), [
            'role' => User::ROLE_MASTER_ADMIN,
        ])->assertSessionHasErrors('role');
        $this->assertSame(User::ROLE_ADMIN, $unverified->fresh()->role);
        $this->assertSame(User::ROLE_ADMIN, $emailUnverified->fresh()->role);

        $property = Property::factory()->published()->create();
        $this->assertTrue($master->can('view', $property));
        $this->actingAs($master)->get(route('admin.properties.index'))->assertOk();
    }

    public function test_master_administrator_login_redirects_to_admin_workspace(): void
    {
        $master = User::factory()->create([
            'role' => User::ROLE_MASTER_ADMIN,
            'password' => 'Master-Admin-Pass-2026!',
        ]);

        $this->post(route('login'), [
            'email' => $master->email,
            'password' => 'Master-Admin-Pass-2026!',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_pending_administrator_can_complete_normal_email_verification_without_automatic_promotion(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();
        $admin = User::factory()->unverified()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'pending-admin@example.test',
            'verification_required_at' => now(),
            'verification_method' => 'email',
        ]);

        $this->actingAs($admin)->get(route('verification.notice'))->assertOk();
        $this->post(route('verification.resend'))->assertSessionHas('status');
        $code = Mail::sent(VerificationCodeMail::class)->last()->code;
        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $admin->refresh();
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNull($admin->verification_required_at);
        $this->assertNotNull($admin->otpVerifications()->value('consumed_at'));
    }

    public function test_new_master_administrator_is_created_unverified_and_must_change_password(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();

        $this->artisan('spacematch:provision-master-admin root@example.test')
            ->expectsQuestion('Administrator name', 'Super Administrator')
            ->expectsQuestion('Initial password (input is hidden)', 'Secure-First-Login-2026!')
            ->expectsQuestion('Confirm initial password (input is hidden)', 'Secure-First-Login-2026!')
            ->expectsConfirmation('Create a new master administrator for root@example.test?', 'yes')
            ->expectsOutput('Pending master administrator account created for root@example.test; a verification code was sent.')
            ->assertSuccessful();

        $master = User::query()->where('email', 'root@example.test')->sole();
        $this->assertSame(User::ROLE_MASTER_ADMIN, $master->role);
        $this->assertNull($master->email_verified_at);
        $this->assertNotNull($master->verification_required_at);
        $this->assertTrue($master->password_change_required);
        $this->assertTrue(Hash::check('Secure-First-Login-2026!', $master->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master_admin.provisioned',
            'description' => json_encode([
                'target_user_id' => $master->id,
                'source' => 'artisan',
                'created' => true,
                'contact_verification_required' => true,
                'password_change_required' => true,
            ]),
        ]);
        Mail::assertSent(\App\Mail\VerificationCodeMail::class);
    }

    public function test_forced_admin_password_change_blocks_workspace_until_changed(): void
    {
        $master = User::factory()->create([
            'role' => User::ROLE_MASTER_ADMIN,
            'password_change_required' => true,
            'password' => 'Temporary-Admin-2026!',
        ]);

        $this->actingAs($master)->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.security.password.edit'));
        $this->get(route('profile'))->assertRedirect(route('admin.security.password.edit'));
        $this->get(route('admin.security.password.edit'))->assertOk();

        $this->patch(route('admin.security.password.update'), [
            'current_password' => 'incorrect',
            'password' => 'New-Secure-Admin-2026!',
            'password_confirmation' => 'New-Secure-Admin-2026!',
        ])->assertSessionHasErrors('current_password');

        $this->patch(route('admin.security.password.update'), [
            'current_password' => 'Temporary-Admin-2026!',
            'password' => 'New-Secure-Admin-2026!',
            'password_confirmation' => 'New-Secure-Admin-2026!',
        ])->assertRedirect(route('admin.dashboard'));

        $master->refresh();
        $this->assertFalse($master->password_change_required);
        $this->assertTrue(Hash::check('New-Secure-Admin-2026!', $master->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $master->id,
            'action' => 'admin.password_changed',
        ]);
        $this->get(route('admin.dashboard'))->assertOk();
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
