<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_and_has_valid_password_toggle(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Welcome back');
        $response->assertSee('data-toggle-password');
    }

    public function test_renter_can_login_and_redirect_to_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Renter User',
            'email' => 'renter@example.com',
            'role' => User::ROLE_RENTER,
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'renter@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/renter/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_admin_account_type(): void
    {
        $response = $this->post('/register', [
            'name' => 'Admin Attempt',
            'email' => 'admin-attempt@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'account_type' => 'admin',
            'terms' => '1', 'privacy_acknowledgment' => '1', 'adult_confirmation' => '1',
        ]);

        $response->assertSessionHasErrors('account_type');
    }

    public function test_guest_is_redirected_to_login_for_protected_dashboard(): void
    {
        $this->get('/owner/dashboard')->assertRedirect('/login');
    }

    public function test_owner_cannot_access_renter_dashboard(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]);

        $this->actingAs($owner)
            ->get('/renter/dashboard')
            ->assertStatus(403);
    }

    public function test_authenticated_header_exposes_profile_and_logout_from_account_menu(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/owner/dashboard')->assertOk()
            ->assertSee('account-menu-toggle', false)
            ->assertSee('Account settings')
            ->assertSee('Log out');
    }
}
