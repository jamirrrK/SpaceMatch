<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomepageExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_carousel_uses_only_images_from_public_available_spaces(): void
    {
        $first = $this->spaceWithPropertyImage('Published Cebu listing');
        $second = $this->spaceWithPropertyImage('Published Manila listing');
        $hidden = $this->spaceWithPropertyImage('Draft listing', 'draft');

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('heroSpaces', fn ($spaces) => $spaces->pluck('id')->sort()->values()->all()
                === collect([$first->id, $second->id])->sort()->values()->all())
            ->assertSee('Featured available rental spaces')
            ->assertSee($first->property->property_name)
            ->assertSee($second->property->property_name)
            ->assertDontSee($hidden->property->property_name)
            ->assertSee(route('property-images.show', $first->property->images()->first()), false);
    }

    public function test_homepage_keeps_an_illustrated_fallback_when_there_are_no_image_listings(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Illustration of a welcoming rental space')
            ->assertDontSee('data-home-carousel', false);
    }

    public function test_renter_message_workspace_does_not_link_to_owner_only_workflows(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);

        $this->actingAs($renter)->get(route('renter.inquiries.index'))
            ->assertOk()
            ->assertSee('Keep your rental conversations in one place.')
            ->assertDontSee(route('owner.requests.index'), false)
            ->assertDontSee(route('owner.inquiries.index'), false)
            ->assertDontSee(route('owner.viewings.index'), false);
    }

    private function spaceWithPropertyImage(string $name, string $status = 'published'): Space
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $property = Property::factory()->published()->create([
            'owner_id' => $owner->id,
            'property_name' => $name,
        ]);
        $space = Space::factory()->create([
            'property_id' => $property->id,
            'listing_status' => $status,
            'availability_status' => 'available',
        ]);
        PropertyImage::query()->create([
            'property_id' => $property->id,
            'image_path' => 'home/'.Str::uuid().'.jpg',
            'is_primary' => true,
            'display_order' => 0,
        ]);

        return $space;
    }
}
