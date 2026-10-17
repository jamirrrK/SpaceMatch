<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyImageRequest;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Services\PropertyImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PropertyImageController extends Controller
{
    public function store(PropertyImageRequest $request, Property $property, PropertyImageService $service)
    {
        $service->upload($property, $request->file('images'), $request->filled('space_id') ? (int) $request->validated('space_id') : null);

        return back()->with('status', 'Images uploaded.');
    }

    public function update(Request $request, PropertyImage $image, PropertyImageService $service)
    {
        Gate::authorize('update', $image);
        $data = $request->validate([
            'is_primary' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'between:0,10000'],
        ]);
        $service->update($image, $data);

        return back()->with('status', 'Image settings updated.');
    }

    public function destroy(PropertyImage $image, PropertyImageService $service)
    {
        Gate::authorize('delete', $image);
        $service->delete($image);

        return back()->with('status', 'Image deleted.');
    }
}
