<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorialOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_offers_a_text_tour_without_referencing_a_missing_video(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Welcome to SpaceMatch!')
            ->assertSee('Watch Tutorial')
            ->assertSee('Skip for Now')
            ->assertSee("Don't Show Again", false)
            ->assertSee('data-tutorial-replay', false)
            ->assertSee('The narrated video is not available yet.')
            ->assertDontSee('<video', false)
            ->assertDontSee('spacematch-tutorial.mp4');
    }

    public function test_owner_tutorial_includes_listing_management_but_not_admin_actions(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/owner/dashboard')->assertOk()
            ->assertSee('Manage a listing')
            ->assertSee('upload photos')
            ->assertDontSee('Review listings');
    }

    public function test_admin_tutorial_includes_moderation_but_not_owner_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('Review listings')
            ->assertSee('moderation status change')
            ->assertDontSee('Manage a listing');
    }

    public function test_renter_tutorial_does_not_advertise_unimplemented_owner_or_admin_tools(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);

        $this->actingAs($renter)->get('/renter/dashboard')->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Manage a listing')
            ->assertDontSee('Review listings');
    }
}