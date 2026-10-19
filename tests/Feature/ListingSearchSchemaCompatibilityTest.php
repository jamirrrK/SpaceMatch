<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ListingSearchSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_search_handles_schema_before_location_migration(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['location_confirmed_at', 'location_visibility']);
        });

        $this->get(route('properties.index', [
            'near_lat' => 17.978733,
            'near_lng' => 120.406866,
            'radius_km' => 10,
            'q' => 'street address',
        ]))->assertOk();
    }

    public function test_map_lists_every_public_space_in_the_philippines_and_links_to_its_property(): void
    {
        $property = Property::factory()->published()->create([
            'property_name' => 'Manila Homes',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'location_visibility' => 'exact',
            'location_confirmed_at' => now(),
        ]);
        $space = Space::factory()->published()->create(['property_id' => $property->id, 'price' => 9000]);
        Space::factory()->published()->create(['property_id' => $property->id, 'price' => 7000]);
        $booking = Booking::query()->create([
            'renter_id' => User::factory()->create(['role' => User::ROLE_RENTER])->id,
            'space_id' => $space->id,
            'start_date' => today()->subMonth(),
            'end_date' => today()->subDay(),
            'status' => 'completed',
        ]);
        Review::query()->create(['booking_id' => $booking->id, 'rating' => 5, 'comment' => 'Good stay.']);

        $outside = Property::factory()->published()->create([
            'latitude' => 35.6762,
            'longitude' => 139.6503,
            'location_visibility' => 'exact',
            'location_confirmed_at' => now(),
        ]);
        Space::factory()->published()->create(['property_id' => $outside->id, 'price' => 8000]);

        $response = $this->getJson(route('map.listings'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Manila Homes')
            ->assertJsonPath('data.0.price', 7000)
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.review_count', 1);

        $this->assertSame(route('properties.show', $property), $response->json('data.0.url'));
    }
}
