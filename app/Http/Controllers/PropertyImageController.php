<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\PropertyImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyImageController extends Controller
{
    public function show(Request $request, PropertyImage $image, PropertyImageService $service)
    {
        $authorized = $request->user()?->can('view', $image->property) ?? false;
        if (! $authorized) {
            abort_unless(Property::publiclyListed()->whereKey($image->property_id)->exists(), 404);
            abort_if($image->space_id && $image->space?->listing_status !== 'published', 404);
        }
        $disk = Storage::disk('property_images');
        $path = $service->safePath($image);
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
