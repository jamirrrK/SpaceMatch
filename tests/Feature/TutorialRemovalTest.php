<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorialRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_role_layouts_do_not_load_the_removed_tutorial(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-tutorial', false)->assertDontSee('Watch Tutorial');
        foreach (['renter', 'owner', 'admin', 'master_admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $url = match($role) { 'renter' => '/renter/dashboard', 'owner' => '/owner/dashboard', default => '/admin/dashboard' };
            $this->actingAs($user)->get($url)->assertOk()->assertDontSee('data-tutorial', false)->assertDontSee('Watch tutorial');
        }
    }
}
