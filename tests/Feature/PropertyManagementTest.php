<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_property_with_safe_initial_status_and_cannot_spoof_ownership(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $this->actingAs($owner)->post('/owner/properties', $this->propertyData([
            'owner_id' => $other->id,
            'status' => 'inactive',
            'listing_status' => 'published',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('properties', [
            'property_name' => 'Luna Residences',
            'owner_id' => $owner->id,
            'status' => 'active',
            'listing_status' => 'draft',
        ]);
    }

    public function test_property_creation_validates_required_fields_and_configured_type(): void
    {
        $this->actingAs($this->owner())->post('/owner/properties', [])
            ->assertSessionHasErrors(['property_name', 'property_type', 'address', 'city']);

        $this->post('/owner/properties', $this->propertyData(['property_type' => 'not-a-rental-type']))
            ->assertSessionHasErrors('property_type');

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_owner_can_edit_archive_and_publish_their_property(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($property->owner)->get('/owner/properties/'.$property->id.'/edit')->assertOk();
        $this->put('/owner/properties/'.$property->id, $this->propertyData(['property_name' => 'Updated Residences']))
            ->assertRedirect()->assertSessionHasNoErrors();
        Space::factory()->published()->create(['property_id' => $property->id]);
        $this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/owner/properties/'.$property->id.'/status', ['status' => 'archived'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'property_name' => 'Updated Residences',
            'listing_status' => 'published',
            'status' => 'archived',
        ]);
    }

    public function test_owner_cannot_read_or_change_another_owners_property(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($this->owner());

        foreach (['', '/edit', '/spaces'] as $suffix) {
            $this->assertContains($this->get('/owner/properties/'.$property->id.$suffix)->status(), [403, 404]);
        }
        $this->assertContains($this->put('/owner/properties/'.$property->id, $this->propertyData())->status(), [403, 404]);
        $this->assertContains($this->patch('/owner/properties/'.$property->id.'/status', ['status' => 'archived'])->status(), [403, 404]);
        $this->assertContains($this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'published'])->status(), [403, 404]);

        $this->assertSame('draft', $property->fresh()->listing_status);
        $this->assertSame('active', $property->fresh()->status);
    }

    public function test_guests_and_renters_cannot_create_properties(): void
    {
        $this->post('/owner/properties', $this->propertyData())->assertRedirect('/login');
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $this->actingAs($renter)->get('/owner/properties/create')->assertForbidden();
        $this->post('/owner/properties', $this->propertyData())->assertForbidden();
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_owner_creates_space_with_amenities_under_the_correct_property(): void
    {
        $property = Property::factory()->create();
        $amenity = Amenity::query()->create(['name' => 'Test Wi-Fi', 'category' => 'Connectivity']);

        $this->actingAs($property->owner)->post('/owner/spaces', $this->spaceData([
            'property_id' => $property->id,
            'amenity_ids' => [$amenity->id],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $space = Space::query()->where('space_name', 'Studio 201')->firstOrFail();
        $this->assertEquals($property->id, $space->property_id);
        $this->assertEquals(8500, $space->price);
        $this->assertSame('draft', $space->listing_status);
        $this->assertDatabaseHas('amenity_space', ['space_id' => $space->id, 'amenity_id' => $amenity->id]);
    }

    public function test_space_rejects_negative_price_zero_capacity_and_duplicate_or_missing_amenities(): void
    {
        $property = Property::factory()->create();
        $amenity = Amenity::query()->create(['name' => 'Test Parking', 'category' => 'Facilities']);
        $this->actingAs($property->owner);

        $this->post('/owner/spaces', $this->spaceData([
            'property_id' => $property->id,
            'price' => -1, 'capacity' => 0, 'amenity_ids' => [$amenity->id, $amenity->id],
        ]))->assertSessionHasErrors(['price', 'capacity', 'amenity_ids.0', 'amenity_ids.1']);
        $this->post('/owner/spaces', $this->spaceData(['property_id' => $property->id, 'amenity_ids' => [999999]]))
            ->assertSessionHasErrors('amenity_ids.0');
        $this->assertDatabaseCount('spaces', 0);
    }

    public function test_space_update_synchronizes_amenities_and_publish_state(): void
    {
        $space = Space::factory()->create();
        $old = Amenity::query()->create(['name' => 'Old Amenity', 'category' => 'Facilities']);
        $new = Amenity::query()->create(['name' => 'New Amenity', 'category' => 'Facilities']);
        $space->amenities()->attach($old);

        $this->actingAs($space->property->owner)->put('/owner/spaces/'.$space->id, $this->spaceData([
            'property_id' => $space->property_id,
            'price' => 9500, 'amenity_ids' => [$new->id], 'availability_status' => 'occupied',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/owner/spaces/'.$space->id.'/publish', ['listing_status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('spaces', ['id' => $space->id, 'price' => 9500, 'availability_status' => 'occupied', 'listing_status' => 'published']);
        $this->assertDatabaseMissing('amenity_space', ['space_id' => $space->id, 'amenity_id' => $old->id]);
        $this->assertDatabaseHas('amenity_space', ['space_id' => $space->id, 'amenity_id' => $new->id]);
    }

    public function test_owner_cannot_create_update_or_publish_spaces_for_another_owner(): void
    {
        $space = Space::factory()->create();
        $this->actingAs($this->owner());

        $this->postJson('/owner/spaces', $this->spaceData(['property_id' => $space->property_id]))
            ->assertUnprocessable()->assertJsonValidationErrors('property_id');
        $this->getJson('/owner/spaces/create?property_id='.$space->property_id)
            ->assertUnprocessable()->assertJsonValidationErrors('property_id');
        $this->assertContains($this->get('/owner/spaces/'.$space->id.'/edit')->status(), [403, 404]);
        $this->assertContains($this->put('/owner/spaces/'.$space->id, $this->spaceData())->status(), [403, 404]);
        $this->assertContains($this->patch('/owner/spaces/'.$space->id.'/publish', ['listing_status' => 'published'])->status(), [403, 404]);
        $this->assertSame('draft', $space->fresh()->listing_status);
        $this->assertDatabaseCount('spaces', 1);
    }

    public function test_owner_dashboard_counts_only_their_actual_properties_and_spaces(): void
    {
        $owner = $this->owner();
        $first = Property::factory()->published()->create(['owner_id' => $owner->id]);
        $second = Property::factory()->create(['owner_id' => $owner->id]);
        Space::factory()->published()->create(['property_id' => $first->id]);
        Space::factory()->create(['property_id' => $first->id, 'availability_status' => 'occupied']);
        Space::factory()->create(['property_id' => $second->id, 'availability_status' => 'unavailable']);
        Space::factory()->published()->count(4)->create();

        $this->actingAs($owner)->get('/owner/dashboard')->assertOk()->assertViewHas('stats', function ($stats) {
            return $stats['total_properties'] === 2
                && $stats['total_spaces'] === 3
                && $stats['published_listings'] === 1
                && $stats['unpublished_listings'] === 2
                && $stats['available_spaces'] === 1
                && $stats['occupied_spaces'] === 1;
        });
    }

    public function test_space_cannot_be_reparented_even_to_another_property_of_the_same_owner(): void
    {
        $space = Space::factory()->create();
        $another = Property::factory()->create(['owner_id' => $space->property->owner_id]);

        $this->actingAs($space->property->owner)->put('/owner/spaces/'.$space->id, $this->spaceData([
            'property_id' => $another->id,
        ]))->assertSessionHasErrors('property_id');

        $this->assertEquals($space->property_id, $space->fresh()->property_id);
    }

    public function test_property_publication_requires_a_published_space_and_unpublishing_hides_it(): void
    {
        $property = Property::factory()->create();
        $space = Space::factory()->create(['property_id' => $property->id]);
        $this->actingAs($property->owner);

        $this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'published'])
            ->assertSessionHasErrors('listing_status');
        $this->assertSame('draft', $property->fresh()->listing_status);
        $this->patch('/owner/spaces/'.$space->id.'/publish', ['listing_status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/properties/'.$property->id)->assertOk();
        $this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'draft'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/properties/'.$property->id)->assertNotFound();
    }

    public function test_complete_owner_public_discovery_and_administrator_workflow_persists_real_records(): void
    {
        Storage::fake('property_images');
        $owner = $this->owner();
        $this->actingAs($owner)->post('/owner/properties', $this->propertyData())
            ->assertRedirect()->assertSessionHasNoErrors();
        $property = Property::query()->where('owner_id', $owner->id)->sole();

        $this->post('/owner/spaces', $this->spaceData(['property_id' => $property->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $space = $property->spaces()->sole();
        $image = UploadedFile::fake()->createWithContent('cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6kQAAAAASUVORK5CYII='
        ));
        $this->post('/owner/properties/'.$property->id.'/images', ['images' => [$image]])
            ->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('property_images')->assertExists($property->images()->sole()->image_path);
        $this->patch('/owner/spaces/'.$space->id.'/publish', ['listing_status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/logout')->assertRedirect('/');

        $this->get('/properties?location=Cebu&max_price=9000')->assertOk()->assertSee($property->property_name);
        $this->get('/properties/'.$property->id)->assertOk()->assertSee($space->space_name);
        $this->get(route('property-images.show', $property->images()->sole()))->assertOk();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get('/admin/properties/'.$property->id)->assertOk()->assertSee($property->property_name);
        $this->patch('/admin/properties/'.$property->id.'/status', [
            'status' => 'inactive', 'reason' => 'Listing is temporarily removed pending an accuracy review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'property.status_changed']);
        $this->get('/properties?location=Cebu')->assertOk()->assertDontSee($property->property_name);
        $this->get('/properties/'.$property->id)->assertNotFound();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function propertyData(array $overrides = []): array
    {
        return array_replace([
            'property_name' => 'Luna Residences',
            'property_type' => 'apartment',
            'description' => 'Quiet apartments near the city center.',
            'address' => '12 Luna Street',
            'barangay' => 'Poblacion',
            'city' => 'Cebu City',
            'province' => 'Cebu',
            'postal_code' => '6000',
        ], $overrides);
    }

    private function spaceData(array $overrides = []): array
    {
        return array_replace([
            'space_name' => 'Studio 201',
            'space_type' => 'studio_unit',
            'description' => 'A bright studio for two people.',
            'price' => 8500,
            'capacity' => 2,
            'floor_area' => 25,
            'floor_number' => '2',
            'availability_status' => 'available',
            'amenity_ids' => [],
        ], $overrides);
    }
}
