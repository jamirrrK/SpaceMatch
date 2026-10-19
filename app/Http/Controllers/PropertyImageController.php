<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\PropertyImageService;
use Illuminate\Http\Request;

class PropertyImageController extends Controller
{
    public function show(Request $request, PropertyImage $image, PropertyImageService $service)
    {
        $authorized = $request->user()?->can('view', $image->property) ?? false;
        if (! $authorized) {
            abort_unless(Property::publiclyListed()->whereKey($image->property_id)->exists(), 404);
            abort_if($image->space_id && $image->space?->listing_status !== 'published', 404);
        }
        $file = $service->readableFile($image);
        abort_unless($file, 404);

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
