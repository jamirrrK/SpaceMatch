<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingFilterRequest;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(ListingFilterRequest $request): View
    {
        $filters = $request->validated();
        $query = Space::publiclyListed()->with([
            'property.images' => fn ($images) => $images->where(fn (Builder $visible) => $visible
                ->whereNull('space_id')->orWhereHas('space', fn (Builder $space) => $space->where('listing_status', 'published'))),
            'images', 'amenities',
        ]);

        $searchTerms = array_values(array_filter([
            $request->input('q'),
            $request->input('location'),
        ], fn ($term) => is_string($term) && trim($term) !== ''));
        $searchTerms = array_map(fn ($term) => trim($term), $searchTerms);

        if (! empty($searchTerms)) {
            $query->where(function (Builder $match) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $like = '%'.$term.'%';
                    $match->orWhere(function (Builder $locationMatch) use ($like): void {
                        $locationMatch->whereHas('property', fn (Builder $property) => $property->where(function (Builder $propertyMatch) use ($like): void {
                            $propertyMatch->where('property_name', 'like', $like)
                                ->orWhere('description', 'like', $like)
                                ->orWhere('city', 'like', $like)
                                ->orWhere('barangay', 'like', $like)
                                ->orWhere('address', 'like', $like)
                                ->orWhere('province', 'like', $like);
                        }));
                    });
                    $match->orWhere('space_name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                }
            });
        }

        if (! empty($filters['property_type'])) {
            $query->whereHas('property', fn (Builder $property) => $property
                ->where('property_type', $filters['property_type']));
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['capacity'])) {
            $query->where('capacity', '>=', $filters['capacity']);
        }

        // One existence constraint per amenity requires every selected amenity.
        foreach ($filters['amenities'] ?? [] as $amenityId) {
            $query->whereHas('amenities', fn (Builder $amenity) => $amenity->whereKey($amenityId));
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price')->orderByDesc('spaces.id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('spaces.id'),
            default => $query->latest('spaces.created_at')->orderByDesc('spaces.id'),
        };

        return view('properties.index', [
            'spaces' => $query->paginate(12)->withQueryString(),
            'amenities' => Amenity::orderBy('category')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Property $property): View
    {
        // Apply the same public visibility rules to a guessed detail URL.
        $property = Property::publiclyListed()->whereKey($property->id)->firstOrFail();
        $property->load([
            'images' => fn ($images) => $images->where(fn (Builder $visible) => $visible
                ->whereNull('space_id')->orWhereHas('space', fn (Builder $space) => $space->where('listing_status', 'published'))),
            'spaces' => fn ($spaces) => $spaces
                ->where('listing_status', 'published')
                ->with(['amenities', 'images'])
                ->orderBy('price')->orderBy('id'),
        ]);

        return view('properties.show', compact('property'));
    }
}
