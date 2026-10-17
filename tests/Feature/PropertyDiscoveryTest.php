<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_find_published_available_spaces_by_city_and_property_name(): void
    {
        $cebu = $this->listing(['property_name' => 'Cebu Sunrise Residence', 'city' => 'Cebu City']);
        $manila = $this->listing(['property_name' => 'Manila River Residence', 'city' => 'Manila', 'province' => 'Metro Manila']);

        $this->get('/properties?location=Cebu')->assertOk()
            ->assertSee($cebu->property->property_name)->assertDontSee($manila->property->property_name);
        $this->get('/properties?q=Sunrise')->assertOk()
            ->assertSee($cebu->property->property_name)->assertDontSee($manila->property->property_name);
    }

    public function test_price_range_filter_returns_only_matching_available_spaces(): void
    {
        $cheap = $this->listing(['property_name' => 'Budget Rental'], ['price' => 2500]);
        $match = $this->listing(['property_name' => 'Central Rental'], ['price' => 8500]);
        $expensive = $this->listing(['property_name' => 'Premium Rental'], ['price' => 20000]);

        $this->get('/properties?min_price=5000&max_price=10000')->assertOk()
            ->assertSee($match->property->property_name)
            ->assertDontSee($cheap->property->property_name)
            ->assertDontSee($expensive->property->property_name);
    }

    public function test_all_filters_apply_to_the_same_space_instead_of_different_spaces_at_a_property(): void
    {
        $property = Property::factory()->published()->create(['property_name' => 'Split Conditions Residence']);
        Space::factory()->published()->create(['property_id' => $property->id, 'price' => 3000, 'capacity' => 1]);
        Space::factory()->published()->create(['property_id' => $property->id, 'price' => 15000, 'capacity' => 5]);
        $match = $this->listing(['property_name' => 'Matching Residence'], ['price' => 6000, 'capacity' => 4]);

        $this->get('/properties?max_price=8000&capacity=3')->assertOk()
            ->assertSee($match->property->property_name)->assertDontSee($property->property_name);
    }

    public function test_property_type_capacity_and_multiple_amenities_filter_results(): void
    {
        $wifi = Amenity::query()->create(['name' => 'Discovery Wi-Fi', 'category' => 'Connectivity']);
        $parking = Amenity::query()->create(['name' => 'Discovery Parking', 'category' => 'Facilities']);
        $match = $this->listing(['property_name' => 'Complete Amenity Home', 'property_type' => 'apartment'], ['capacity' => 3]);
        $match->amenities()->attach([$wifi->id, $parking->id]);
        $partial = $this->listing(['property_name' => 'Partial Amenity Home', 'property_type' => 'apartment'], ['capacity' => 3]);
        $partial->amenities()->attach($wifi->id);
        $small = $this->listing(['property_name' => 'Tiny Home', 'property_type' => 'apartment'], ['capacity' => 1]);
        $small->amenities()->attach([$wifi->id, $parking->id]);
        $otherType = $this->listing(['property_name' => 'Condominium Home', 'property_type' => 'condominium'], ['capacity' => 3]);
        $otherType->amenities()->attach([$wifi->id, $parking->id]);
        $query = http_build_query(['property_type' => 'apartment', 'capacity' => 2, 'amenities' => [$wifi->id, $parking->id]]);

        $this->get('/properties?'.$query)->assertOk()->assertSee($match->property->property_name)
            ->assertDontSee($partial->property->property_name)
            ->assertDontSee($small->property->property_name)
            ->assertDontSee($otherType->property->property_name);
    }

    public function test_unpublished_archived_inactive_and_unavailable_records_are_hidden_from_browsing_and_direct_urls(): void
    {
        $draftProperty = $this->listing(['property_name' => 'Hidden Draft Property', 'listing_status' => 'draft']);
        $archived = $this->listing(['property_name' => 'Hidden Archived Property', 'status' => 'archived']);
        $inactive = $this->listing(['property_name' => 'Hidden Inactive Property', 'status' => 'inactive']);
        $draftSpace = $this->listing(['property_name' => 'Hidden Draft Space Property'], ['listing_status' => 'draft']);
        $occupied = $this->listing(['property_name' => 'Hidden Occupied Property'], ['availability_status' => 'occupied']);
        $visible = $this->listing(['property_name' => 'Visible Public Property']);

        $response = $this->get('/properties')->assertOk()->assertSee($visible->property->property_name);
        foreach ([$draftProperty, $archived, $inactive, $draftSpace, $occupied] as $hidden) {
            $response->assertDontSee($hidden->property->property_name);
            $this->get('/properties/'.$hidden->property_id)->assertNotFound();
            $this->get('/spaces/'.$hidden->id)->assertNotFound();
        }
        $this->get('/spaces')->assertRedirect('/properties');
    }

    public function test_public_property_detail_hides_draft_spaces_and_shows_availability_of_published_spaces(): void
    {
        $visible = $this->listing(['property_name' => 'Public Detail Property'], ['space_name' => 'Visible Studio']);
        $draft = Space::factory()->create(['property_id' => $visible->property_id, 'space_name' => 'Secret Draft Studio']);
        $occupied = Space::factory()->published()->create([
            'property_id' => $visible->property_id, 'space_name' => 'Occupied Private Studio', 'availability_status' => 'occupied',
        ]);

        $this->get('/properties/'.$visible->property_id)->assertOk()->assertSee($visible->space_name)
            ->assertDontSee($draft->space_name)->assertSee($occupied->space_name);
    }

    public function test_discovery_validates_sort_price_range_capacity_and_unknown_amenities(): void
    {
        $this->getJson('/properties?sort=DROP%20TABLE%20properties&min_price=-1&capacity=0&property_type=invalid&amenities[]=999999')
            ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'min_price', 'capacity', 'property_type', 'amenities.0']);
        $this->getJson('/properties?min_price=9000&max_price=1000')
            ->assertUnprocessable()->assertJsonValidationErrors('max_price');
    }

    public function test_discovery_sorts_prices_and_newest_first(): void
    {
        $cheap = $this->listing(['property_name' => 'First Price Home'], ['price' => 2500, 'created_at' => now()->subDays(3)]);
        $medium = $this->listing(['property_name' => 'Middle Price Home'], ['price' => 7500, 'created_at' => now()->subDays(2)]);
        $expensive = $this->listing(['property_name' => 'Last Price Home'], ['price' => 15000, 'created_at' => now()->subDay()]);

        $this->get('/properties?sort=price_asc')->assertOk()->assertSeeInOrder([
            $cheap->property->property_name, $medium->property->property_name, $expensive->property->property_name,
        ]);
        $this->get('/properties?sort=price_desc')->assertOk()->assertSeeInOrder([
            $expensive->property->property_name, $medium->property->property_name, $cheap->property->property_name,
        ]);
        $this->get('/properties?sort=newest')->assertOk()->assertSeeInOrder([
            $expensive->property->property_name, $medium->property->property_name, $cheap->property->property_name,
        ]);
    }

    public function test_empty_discovery_results_render_successfully(): void
    {
        $this->get('/properties?location=Nowhere')->assertOk()->assertViewHas('spaces', fn ($spaces) => $spaces->isEmpty());
    }

    public function test_legacy_available_property_status_remains_discoverable_when_published(): void
    {
        $legacy = $this->listing(['property_name' => 'Legacy Published Home', 'status' => 'available']);
        $this->get('/properties')->assertOk()->assertSee($legacy->property->property_name);
        $this->get('/properties/'.$legacy->property_id)->assertOk();
    }

    public function test_discovery_filters_bedrooms_bathrooms_move_in_date_and_amenities(): void
    {
        $wifi = Amenity::query()->where('name', 'Wi-Fi')->firstOrFail();
        $match = $this->listing(['property_name' => 'Discovery Match'], [
            'bedrooms' => 3,
            'bathrooms' => 2,
            'available_from' => now()->addDays(20)->toDateString(),
        ]);
        $match->amenities()->attach($wifi);
        $tooFewRooms = $this->listing(['property_name' => 'Too Few Rooms'], [
            'bedrooms' => 1,
            'bathrooms' => 2,
            'available_from' => now()->addDays(10)->toDateString(),
        ]);
        $tooLate = $this->listing(['property_name' => 'Too Late'], [
            'bedrooms' => 3,
            'bathrooms' => 2,
            'available_from' => now()->addDays(40)->toDateString(),
        ]);

        $this->get('/properties?'.http_build_query([
            'bedrooms' => 2,
            'bathrooms' => 2,
            'available_from' => now()->addDays(30)->toDateString(),
            'amenities' => [$wifi->id],
        ]))->assertOk()->assertSee($match->property->property_name)
            ->assertDontSee($tooFewRooms->property->property_name)
            ->assertDontSee($tooLate->property->property_name);
    }

    public function test_search_can_match_amenity_and_recently_updated_sort_is_supported(): void
    {
        $wifi = Amenity::query()->where('name', 'Wi-Fi')->firstOrFail();
        $first = $this->listing(['property_name' => 'Earlier Update'], ['updated_at' => now()->subDay()]);
        $first->amenities()->attach($wifi);
        $second = $this->listing(['property_name' => 'Later Update'], ['updated_at' => now()]);

        $this->get('/properties?q=Wi-Fi')->assertOk()->assertSee($first->property->property_name)
            ->assertDontSee($second->property->property_name);
        $this->get('/properties?sort=updated')->assertOk()->assertSeeInOrder([
            $second->property->property_name,
            $first->property->property_name,
        ]);
    }

    public function test_available_by_filter_excludes_spaces_with_fully_booked_capacity_on_that_date(): void
    {
        $moveIn = now()->addDays(20)->toDateString();
        $space = $this->listing(['property_name' => 'Fully Booked On Date'], ['capacity' => 1]);
        Booking::query()->create([
            'renter_id' => User::factory()->create(['role' => User::ROLE_RENTER])->id,
            'space_id' => $space->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'confirmed',
        ]);
        $available = $this->listing(['property_name' => 'Actually Available'], ['capacity' => 1]);

        $this->get('/properties?available_from='.$moveIn)->assertOk()
            ->assertSee($available->property->property_name)
            ->assertDontSee($space->property->property_name);
    }

    private function listing(array $propertyAttributes = [], array $spaceAttributes = []): Space
    {
        $property = Property::factory()->published()->create($propertyAttributes);

        return Space::factory()->published()->create(array_replace(['property_id' => $property->id], $spaceAttributes));
    }
}
