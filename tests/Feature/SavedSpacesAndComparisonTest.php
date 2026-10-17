<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSpacesAndComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_renters_can_save_and_remove_only_their_own_published_spaces(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $otherRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $space = $this->publishedSpace();

        $this->actingAs($renter)->post(route('renter.saved-spaces.store', $space))->assertRedirect();
        $this->post(route('renter.saved-spaces.store', $space))->assertRedirect();
        $this->assertDatabaseCount('saved_spaces', 1);

        $this->get(route('renter.saved-spaces.index'))->assertOk()
            ->assertSee($space->space_name)->assertSee('Remove saved')->assertSee('Remove from saved spaces');
        $this->actingAs($otherRenter)->get(route('renter.saved-spaces.index'))->assertOk()->assertDontSee($space->space_name);

        $this->actingAs($renter)->delete(route('renter.saved-spaces.destroy', $space))->assertRedirect();
        $this->assertDatabaseCount('saved_spaces', 0);
    }

    public function test_saved_space_actions_are_limited_to_renters_and_public_spaces(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $space = $this->publishedSpace();
        $draft = Space::factory()->create(['property_id' => $space->property_id]);

        $this->actingAs($owner)->post(route('renter.saved-spaces.store', $space))->assertForbidden();
        $this->actingAs($renter)->post(route('renter.saved-spaces.store', $draft))->assertNotFound();
        $this->assertDatabaseCount('saved_spaces', 0);
    }

    public function test_comparison_is_limited_to_three_publicly_listed_spaces_and_can_remove_entries(): void
    {
        $spaces = collect(range(1, 4))->map(fn () => $this->publishedSpace());

        foreach ($spaces->take(3) as $space) {
            $this->post(route('spaces.compare.store', $space))->assertRedirect();
        }

        $this->post(route('spaces.compare.store', $spaces[3]))->assertRedirect()->assertSessionHas('error');
        $this->get(route('spaces.compare.index'))->assertOk()
            ->assertSee($spaces[0]->space_name)->assertSee($spaces[1]->space_name)->assertSee($spaces[2]->space_name)
            ->assertDontSee($spaces[3]->space_name);

        $this->delete(route('spaces.compare.destroy', $spaces[0]))->assertRedirect();
        $this->post(route('spaces.compare.store', $spaces[3]))->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('spaces.compare.index'))->assertOk()->assertSee($spaces[3]->space_name);
    }

    private function publishedSpace(): Space
    {
        $property = Property::factory()->published()->create();

        return Space::factory()->published()->create(['property_id' => $property->id]);
    }
}
