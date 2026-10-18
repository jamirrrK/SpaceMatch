<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenterPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_can_store_and_update_housing_preferences(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);

        $this->actingAs($renter)->get(route('renter.preferences.edit'))->assertOk();
        $data = [
            'school_or_campus' => 'Cebu City Campus',
            'move_in_date' => now()->addMonth()->toDateString(),
            'budget_min' => 5000,
            'budget_max' => 12000,
            'preferred_location' => 'Lahug',
            'space_type' => 'studio_unit',
            'capacity' => 2,
            'amenities_notes' => 'Reliable study Wi-Fi',
        ];

        $this->put(route('renter.preferences.update'), $data)->assertRedirect(route('renter.preferences.edit'));
        $this->assertDatabaseHas('renter_preferences', [
            'user_id' => $renter->id,
            'school_or_campus' => 'Cebu City Campus',
            'preferred_location' => 'Lahug',
            'budget_min' => 5000,
            'budget_max' => 12000,
            'space_type' => 'studio_unit',
        ]);

        $this->put(route('renter.preferences.update'), [...$data, 'school_or_campus' => 'Updated Campus']);
        $this->assertDatabaseCount('renter_preferences', 1);
        $this->assertDatabaseHas('renter_preferences', ['user_id' => $renter->id, 'school_or_campus' => 'Updated Campus']);
    }

    public function test_preference_validation_and_role_access_are_enforced(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $this->actingAs($renter)->put(route('renter.preferences.update'), [
            'move_in_date' => now()->subDay()->toDateString(),
            'budget_min' => 10000,
            'budget_max' => 5000,
            'space_type' => 'not-a-space-type',
            'capacity' => 0,
        ])->assertSessionHasErrors(['move_in_date', 'budget_max', 'space_type', 'capacity']);
        $this->assertDatabaseCount('renter_preferences', 0);

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->get(route('renter.preferences.edit'))->assertForbidden();
        $this->put(route('renter.preferences.update'), [])->assertForbidden();
    }
}
