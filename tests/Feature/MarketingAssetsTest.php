<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_pending_application_metrics_follow_the_real_request_workflow(): void
    {
        $renter = \App\Models\User::factory()->create(['role' => 'renter']);
        $space = \App\Models\Space::factory()->create();
        foreach (['pending', 'more_information_requested', 'approved', 'rejected', 'cancelled'] as $status) {
            \App\Models\RentalRequest::create([
                'renter_id' => $renter->id, 'space_id' => $space->id, 'move_in_date' => now()->addMonth(),
                'occupants' => 1, 'duration_months' => 3, 'contact_phone' => '09170000000', 'status' => $status,
            ]);
        }
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['pending_booking_requests'] === 2 && $stats['pending_requests'] === 2);
    }

    public function test_illustrative_discovery_links_use_real_filters_and_keep_listings_separate(): void
    {
        $page = $this->get('/')->assertOk();
        foreach (['bedspace', 'dormitory', 'apartment', 'private_room', 'shared_room'] as $type) {
            $page->assertSee(route('properties.index', ['space_types' => [$type]]));
        }
        foreach (['Manila', 'Cebu City', 'Davao City', 'Baguio City', 'Iloilo City'] as $city) {
            $page->assertSee(route('properties.index', ['location' => $city]));
        }
        $page->assertSee('not a rental listing')->assertSee('owner reported')->assertSee('No featured spaces right now');
    }

    public function test_all_prepared_images_exist_are_decodable_and_never_upscale(): void
    {
        $images = config('marketing.images');
        $this->assertCount(17, $images);
        foreach ($images as $image) {
            foreach ($image['variants'] as $variant) {
                $this->assertLessThanOrEqual($image['width'], $variant['width']);
                foreach (['jpg', 'webp'] as $format) {
                    $file = public_path($variant[$format]);
                    $this->assertFalse(is_link($file));
                    $this->assertFileExists($file);
                    $dimensions = getimagesize($file);
                    $this->assertSame($variant['width'], $dimensions[0]);
                    $this->assertEqualsWithDelta($image['width'] / $image['height'], $dimensions[0] / $dimensions[1], .02);
                }
            }
        }
    }
}
