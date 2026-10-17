<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PropertyImageService
{
    public function upload(Property $property, array $files, ?int $spaceId): void
    {
        $stored = [];
        try {
            DB::transaction(function () use ($property, $files, $spaceId, &$stored) {
                Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
                if ($property->images()->count() + count($files) > 30) {
                    throw ValidationException::withMessages(['images' => 'A property can have up to 30 images. Delete an image before adding more.']);
                }
                if ($spaceId && ! $property->spaces()->whereKey($spaceId)->exists()) {
                    throw ValidationException::withMessages(['space_id' => 'Choose a rental space belonging to this property.']);
                }
                $scope = $property->images()->where('space_id', $spaceId);
                $hasPrimary = (clone $scope)->where('is_primary', true)->exists();
                $order = (int) $scope->max('display_order');
                foreach ($files as $file) {
                    $path = $file->store('property-images/'.$property->id, 'property_images');
                    if (! $path) {
                        throw new RuntimeException('Image storage failed.');
                    }
                    $stored[] = $path;
                    $property->images()->create([
                        'space_id' => $spaceId, 'image_path' => $path,
                        'is_primary' => ! $hasPrimary, 'display_order' => ++$order,
                    ]);
                    $hasPrimary = true;
                }
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk('property_images')->delete($path);
            }
            throw $exception;
        }
    }

    public function update(PropertyImage $image, array $data): void
    {
        DB::transaction(function () use ($image, $data) {
            Property::whereKey($image->property_id)->lockForUpdate()->firstOrFail();
            $locked = PropertyImage::whereKey($image->id)->firstOrFail();
            if (! empty($data['is_primary'])) {
                PropertyImage::where('property_id', $locked->property_id)->where('space_id', $locked->space_id)->update(['is_primary' => false]);
            }
            $locked->update($data);
            $this->ensurePrimary($locked->property_id, $locked->space_id);
        });
    }

    public function delete(PropertyImage $image): void
    {
        $disk = Storage::disk('property_images');
        $path = $this->safePath($image);
        $trash = 'property-image-trash/'.Str::uuid().'.'.pathinfo($path, PATHINFO_EXTENSION);
        $moved = false;
        try {
            DB::transaction(function () use ($image, $disk, $path, $trash, &$moved) {
                Property::whereKey($image->property_id)->lockForUpdate()->firstOrFail();
                $locked = PropertyImage::whereKey($image->id)->firstOrFail();
                if ($disk->exists($path)) {
                    if (! $disk->move($path, $trash)) {
                        throw new RuntimeException('Unable to remove the image file.');
                    }
                    $moved = true;
                }
                $locked->delete();
                $this->ensurePrimary($locked->property_id, $locked->space_id);
            });
        } catch (Throwable $exception) {
            if ($moved && ! $disk->move($trash, $path)) {
                Log::error('Image rollback restoration failed.', ['image_id' => $image->id]);
            }
            throw $exception;
        }
        if ($moved && ! $disk->delete($trash)) {
            Log::warning('Image trash cleanup pending.', ['image_id' => $image->id]);
        }
    }

    public function safePath(PropertyImage $image): string
    {
        $prefix = 'property-images/'.$image->property_id.'/';
        if (! str_starts_with($image->image_path, $prefix) || ! preg_match('/\A[a-zA-Z0-9]+\.(?:jpg|jpeg|png|webp)\z/i', substr($image->image_path, strlen($prefix)))) {
            abort(404);
        }

        return $image->image_path;
    }

    private function ensurePrimary(int $propertyId, ?int $spaceId): void
    {
        $scope = PropertyImage::where('property_id', $propertyId)->where('space_id', $spaceId);
        if (! (clone $scope)->where('is_primary', true)->exists()) {
            $scope->orderBy('display_order')->orderBy('id')->first()?->update(['is_primary' => true]);
        }
    }
}
