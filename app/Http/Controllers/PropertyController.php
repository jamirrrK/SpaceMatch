<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingFilterRequest;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Review;
use App\Models\Space;
use App\Models\User;
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
        if ($request->user()?->hasRole('renter')) {
            $query->withExists(['savedByUsers as is_saved' => fn (Builder $users) => $users->whereKey($request->user()->id)]);
        }

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
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('amenities', fn (Builder $amenity) => $amenity->where('name', 'like', $like));
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

        foreach (['bedrooms', 'bathrooms'] as $countFilter) {
            if (isset($filters[$countFilter])) {
                $query->where($countFilter, '>=', $filters[$countFilter]);
            }
        }

        if (isset($filters['available_from'])) {
            $query->where(fn (Builder $availability) => $availability->whereNull('available_from')
                ->orWhereDate('available_from', '<=', $filters['available_from']));
            $query->whereRaw(
                'COALESCE((SELECT SUM(bookings.occupants) FROM bookings WHERE bookings.space_id = spaces.id AND bookings.status IN (?, ?) AND bookings.start_date <= ? AND bookings.end_date >= ?), 0) < spaces.capacity',
                ['confirmed', 'active', $filters['available_from'], $filters['available_from']]
            );
        }

        // One existence constraint per amenity requires every selected amenity.
        foreach ($filters['amenities'] ?? [] as $amenityId) {
            $query->whereHas('amenities', fn (Builder $amenity) => $amenity->whereKey($amenityId));
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price')->orderByDesc('spaces.id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('spaces.id'),
            'updated' => $query->orderByDesc('spaces.updated_at')->orderByDesc('spaces.id'),
            'relevant' => $query->orderByRaw(
                'CASE WHEN spaces.space_name = ? THEN 0 WHEN spaces.space_name LIKE ? THEN 1 ELSE 2 END',
                [$request->input('q', ''), $request->input('q', '').'%']
            )->latest('spaces.created_at')->orderByDesc('spaces.id'),
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
            'owner',
            'images' => fn ($images) => $images->where(fn (Builder $visible) => $visible
                ->whereNull('space_id')->orWhereHas('space', fn (Builder $space) => $space->where('listing_status', 'published'))),
            'spaces' => fn ($spaces) => $spaces
                ->where('listing_status', 'published')
                ->when(auth()->user()?->hasRole('renter'), fn ($spaces) => $spaces->withExists([
                    'savedByUsers as is_saved' => fn (Builder $users) => $users->whereKey(auth()->id()),
                ]))
                ->with(['amenities', 'images'])
                ->orderBy('price')->orderBy('id'),
        ]);
        $reviewQuery = Review::query()
            ->with('booking.renter')
            ->whereHas('booking.space', fn (Builder $space) => $space->where('property_id', $property->id))
            ->whereHas('booking', fn (Builder $booking) => $booking->where('status', 'completed'));
        $rating = [
            'average' => (clone $reviewQuery)->avg('rating'),
            'count' => (clone $reviewQuery)->count(),
        ];
        $reviews = $reviewQuery
            ->latest()
            ->take(10)
            ->get();

        $isSaved = auth()->check()
            && auth()->user()->role === User::ROLE_RENTER
            && auth()->user()->savedProperties()->whereKey($property->id)->exists();

        return view('properties.show', compact('property', 'isSaved', 'reviews', 'rating'));
    }
}
