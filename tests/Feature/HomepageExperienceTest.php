<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('property_images');
    }

    public function test_carousel_skips_missing_and_invalid_uploads_before_using_valid_property_photo(): void
    {
        $space = $this->spaceWithPropertyImage('Valid property cover');
        $space->images()->create(['property_id' => $space->property_id, 'image_path' => 'property-images/'.$space->property_id.'/missing.png']);
        $badPath = 'property-images/'.$space->property_id.'/invalid.png';
        Storage::disk('property_images')->put($badPath, '<html>Not an image</html>');
        $space->images()->create(['property_id' => $space->property_id, 'image_path' => $badPath]);
        $cover = $space->property->images()->whereNull('space_id')->first();
        $this->get('/')->assertOk()->assertViewHas('heroMedia', fn ($media) => $media[$space->id]['url'] === $cover->url);
        $this->get($cover->url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('property-images.show', $space->images()->where('image_path', $badPath)->first()))->assertNotFound();
    }

    public function test_available_listing_without_usable_photos_gets_labeled_category_illustration(): void
    {
        $space = $this->spaceWithPropertyImage('Room with missing photo');
        Storage::disk('property_images')->delete($space->property->images()->first()->image_path);
        $this->get('/')->assertOk()->assertSee('Room with missing photo')->assertSee('Illustrative image')
            ->assertViewHas('heroMedia', fn ($media) => $media[$space->id]['url'] === null);
        $this->get($space->property->images()->first()->url)->assertNotFound();
    }

    public function test_valid_space_upload_takes_priority_over_a_property_cover(): void
    {
        $space = $this->spaceWithPropertyImage('Property cover');
        $path = 'property-images/'.$space->property_id.'/room.png';
        Storage::disk('property_images')->copy($space->property->images()->first()->image_path, $path);
        $upload = $space->images()->create(['property_id' => $space->property_id, 'image_path' => $path]);
        $this->get('/')->assertOk()->assertViewHas('heroMedia', fn ($media) => $media[$space->id]['url'] === $upload->url);
    }

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
            ->assertSee('Promotional artwork, not a rental listing.')
            ->assertSee('data-home-carousel', false)
            ->assertDontSee('Featured available rental spaces');
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
        $path = 'property-images/'.$property->id.'/'.Str::random(20).'.png';
        Storage::disk('property_images')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6kQAAAAASUVORK5CYII='));
        PropertyImage::query()->create([
            'property_id' => $property->id,
            'image_path' => $path,
            'is_primary' => true,
            'display_order' => 0,
        ]);

        return $space;
    }
}
