<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SavedPropertyController extends Controller
{
    public function index(Request $request): View
    {
        $properties = $request->user()->savedProperties()
            ->with([
                'images' => fn ($images) => $images->where(fn ($visible) => $visible
                    ->whereNull('space_id')
                    ->orWhereHas('space', fn ($space) => $space->where('listing_status', 'published'))),
                'spaces' => fn ($spaces) => $spaces->with(['images', 'amenities'])->orderBy('price'),
            ])
            ->latest('saved_properties.created_at')
            ->paginate(12);

        return view('renter.saved-properties.index', compact('properties'));
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        if (! Property::publiclyListed()->whereKey($property->id)->exists()) {
            throw ValidationException::withMessages(['property' => 'Only published properties with available spaces can be saved.']);
        }

        $request->user()->savedProperties()->syncWithoutDetaching([$property->id]);

        return back()->with('status', 'Property saved to your list.');
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        $request->user()->savedProperties()->detach($property->id);

        return back()->with('status', 'Property removed from your saved list.');
    }
}
