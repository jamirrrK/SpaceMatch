<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('property_images');
    }

    public function test_owner_uploads_multiple_valid_images_and_associates_them_with_a_space(): void
    {
        $space = Space::factory()->create();

        $this->actingAs($space->property->owner)->post('/owner/properties/'.$space->property_id.'/images', [
            'space_id' => $space->id,
            'images' => [$this->image('front.png'), $this->image('room.png')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $images = PropertyImage::query()->orderBy('display_order')->get();
        $this->assertCount(2, $images);
        $this->assertCount(1, $images->where('is_primary', true));
        foreach ($images as $image) {
            $this->assertEquals($space->property_id, $image->property_id);
            $this->assertEquals($space->id, $image->space_id);
            Storage::disk('property_images')->assertExists($image->image_path);
            $this->assertStringNotContainsString('..', $image->image_path);
            $this->assertNotSame('front.png', basename($image->image_path));
            $this->assertNotSame('room.png', basename($image->image_path));
        }
    }

    public function test_upload_rejects_unsupported_files_spoofed_images_and_oversized_files(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($property->owner);

        foreach ([
            UploadedFile::fake()->createWithContent('payload.php', '<?php echo "unsafe";'),
            UploadedFile::fake()->createWithContent('fake.png', 'This is not an image.'),
            UploadedFile::fake()->create('large.png', 5121, 'image/png'),
        ] as $file) {
            $this->post('/owner/properties/'.$property->id.'/images', ['images' => [$file]])
                ->assertSessionHasErrors('images.0');
        }

        $this->assertDatabaseCount('property_images', 0);
        $this->assertEmpty(Storage::disk('property_images')->allFiles());
    }

    public function test_upload_enforces_batch_size_and_total_per_property_limit(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($property->owner)->post('/owner/properties/'.$property->id.'/images', [
            'images' => array_map(fn ($number) => $this->image('photo-'.$number.'.png'), range(1, 11)),
        ])->assertSessionHasErrors('images');

        for ($number = 1; $number <= 30; $number++) {
            $property->images()->create([
                'image_path' => 'property-images/'.$property->id.'/existing'.$number.'.png',
                'is_primary' => $number === 1,
                'display_order' => $number,
            ]);
        }
        $this->post('/owner/properties/'.$property->id.'/images', ['images' => [$this->image()]])
            ->assertSessionHasErrors('images');
        $this->assertDatabaseCount('property_images', 30);
        $this->assertEmpty(Storage::disk('property_images')->allFiles());
    }

    public function test_upload_cannot_attach_another_propertys_space_even_with_the_same_owner(): void
    {
        $property = Property::factory()->create();
        $otherProperty = Property::factory()->create(['owner_id' => $property->owner_id]);
        $otherSpace = Space::factory()->create(['property_id' => $otherProperty->id]);

        $this->actingAs($property->owner)->post('/owner/properties/'.$property->id.'/images', [
            'space_id' => $otherSpace->id,
            'images' => [$this->image()],
        ])->assertSessionHasErrors('space_id');

        $this->assertDatabaseCount('property_images', 0);
        $this->assertEmpty(Storage::disk('property_images')->allFiles());
    }

    public function test_upload_rejects_aggregate_size_over_thirty_megabytes_even_when_each_image_is_valid(): void
    {
        $property = Property::factory()->create();
        $images = array_map(fn ($number) => $this->image('photo-'.$number.'.png')->size(5120), range(1, 7));

        $this->actingAs($property->owner)->post('/owner/properties/'.$property->id.'/images', ['images' => $images])
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('property_images', 0);
        $this->assertEmpty(Storage::disk('property_images')->allFiles());
    }

    public function test_owner_cannot_upload_or_modify_or_delete_another_owners_images(): void
    {
        $property = Property::factory()->create();
        $image = $this->storedImage($property);
        $other = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($other);

        $this->assertContains($this->post('/owner/properties/'.$property->id.'/images', ['images' => [$this->image()]])->status(), [403, 404]);
        $this->assertContains($this->patch('/owner/images/'.$image->id, ['is_primary' => true, 'display_order' => 50])->status(), [403, 404]);
        $this->assertContains($this->delete('/owner/images/'.$image->id)->status(), [403, 404]);

        $this->assertDatabaseHas('property_images', ['id' => $image->id, 'display_order' => 0]);
        Storage::disk('property_images')->assertExists($image->image_path);
    }

    public function test_primary_image_selection_ordering_and_deletion_keep_files_and_records_consistent(): void
    {
        $property = Property::factory()->create();
        $first = $this->storedImage($property, 'first.png', true, 0);
        $second = $this->storedImage($property, 'second.png', false, 1);
        $this->actingAs($property->owner);

        $this->patch('/owner/images/'.$second->id, ['is_primary' => true, 'display_order' => 7])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse((bool) $first->fresh()->is_primary);
        $this->assertTrue((bool) $second->fresh()->is_primary);
        $this->assertEquals(7, $second->fresh()->display_order);
        $this->assertSame(1, $property->images()->where('is_primary', true)->count());

        $this->delete('/owner/images/'.$second->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('property_images', ['id' => $second->id]);
        Storage::disk('property_images')->assertMissing($second->image_path);
        Storage::disk('property_images')->assertExists($first->image_path);
        $this->assertTrue((bool) $first->fresh()->is_primary);
    }

    public function test_image_order_and_primary_updates_are_validated(): void
    {
        $property = Property::factory()->create();
        $image = $this->storedImage($property);

        $this->actingAs($property->owner)->patch('/owner/images/'.$image->id, ['display_order' => -1, 'is_primary' => 'invalid'])
            ->assertSessionHasErrors(['display_order', 'is_primary']);
        $this->assertEquals(0, $image->fresh()->display_order);
    }

    public function test_draft_property_images_are_only_accessible_to_the_owner_and_administrator(): void
    {
        $property = Property::factory()->create();
        $image = $this->storedImage($property);
        $url = route('property-images.show', $image);

        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))->get($url)->assertNotFound();
        $this->actingAs($property->owner)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get($url)->assertOk();
    }

    public function test_public_image_access_requires_published_parent_and_published_space(): void
    {
        $property = Property::factory()->published()->create();
        Space::factory()->published()->create(['property_id' => $property->id]);
        $cover = $this->storedImage($property, 'cover.png');
        $draftSpace = Space::factory()->create(['property_id' => $property->id]);
        $draftImage = $this->storedImage($property, 'draft.png');
        $draftImage->update(['space_id' => $draftSpace->id]);

        $this->get(route('property-images.show', $cover))->assertOk();
        $this->get(route('property-images.show', $draftImage))->assertNotFound();

        $property->update(['status' => 'archived']);
        $this->get(route('property-images.show', $cover))->assertNotFound();
    }

    public function test_image_serving_and_deletion_reject_paths_outside_the_property_image_folder(): void
    {
        $property = Property::factory()->create();
        Storage::disk('property_images')->put('unrelated.txt', 'Keep this file.');
        $image = $property->images()->create(['image_path' => '../unrelated.txt', 'is_primary' => true, 'display_order' => 0]);

        $this->actingAs($property->owner)->get(route('property-images.show', $image))->assertNotFound();
        $this->delete('/owner/images/'.$image->id)->assertNotFound();

        Storage::disk('property_images')->assertExists('unrelated.txt');
        $this->assertDatabaseHas('property_images', ['id' => $image->id]);
    }

    public function test_property_media_root_is_separate_from_the_public_storage_link(): void
    {
        // Storage::fake only swaps the adapter, so the configured roots remain testable.
        $propertyRoot = str_replace('\\', '/', config('filesystems.disks.property_images.root'));
        $publicRoot = str_replace('\\', '/', config('filesystems.disks.public.root'));
        $linkedRoot = str_replace('\\', '/', config('filesystems.links')[public_path('storage')]);

        $this->assertNotSame($publicRoot, $propertyRoot);
        $this->assertFalse(str_starts_with($propertyRoot.'/', rtrim($publicRoot, '/').'/'));
        $this->assertFalse(str_starts_with($propertyRoot.'/', rtrim($linkedRoot, '/').'/'));
    }

    private function image(string $name = 'test.png'): UploadedFile
    {
        // A real 1 x 1 PNG makes image validation meaningful without requiring GD.
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6kQAAAAASUVORK5CYII='
        ));
    }

    private function storedImage(Property $property, string $name = 'existing.png', bool $primary = true, int $order = 0): PropertyImage
    {
        $path = 'property-images/'.$property->id.'/'.$name;
        Storage::disk('property_images')->put($path, $this->image($name)->getContent());

        return $property->images()->create(['image_path' => $path, 'is_primary' => $primary, 'display_order' => $order]);
    }
}
