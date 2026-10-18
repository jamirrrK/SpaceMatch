<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingFilterRequest;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Review;
use App\Models\Space;
use App\Models\User;
use App\Services\StudentDiscovery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(ListingFilterRequest $request): View
    {
        $filters = $request->validated();
        $query = \App\Services\ListingSearch::query($filters, $request->user());

        return view('properties.index', [
            'spaces' => $query->paginate(12)->withQueryString(),
            'amenities' => Amenity::orderBy('category')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Property $property): View
    {
        // Apply the same public visibility rules to a guessed detail URL.
        $property = Property::publiclyListed()->withReviewSummary()->whereKey($property->id)->firstOrFail();
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
        $reviewQuery = Review::query()->whereNull('hidden_at')
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
