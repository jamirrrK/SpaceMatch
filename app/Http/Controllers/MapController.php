<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingFilterRequest;
use App\Models\Property;
use App\Services\ListingSearch;

class MapController extends Controller
{
    public function index(ListingFilterRequest $request)
    {
        $minimumPrices = ListingSearch::query($request->validated(), $request->user())
            ->setEagerLoads([])
            ->reorder()
            ->selectRaw('spaces.property_id, MIN(spaces.price) as minimum_price')
            ->groupBy('spaces.property_id')
            ->get()
            ->keyBy('property_id');

        $data = Property::query()
            ->withReviewSummary()
            ->whereKey($minimumPrices->keys())
            ->get()
            ->map(function (Property $property) use ($minimumPrices) {
                $location = $property->publicLocation();
                if (! $location || $location['lat'] < 4 || $location['lat'] > 22 || $location['lng'] < 116 || $location['lng'] > 127) {
                    return null;
                }

                return [
                    'id' => $property->id,
                    'name' => $property->property_name,
                    'city' => $property->city,
                    'province' => $property->province,
                    'price' => (float) $minimumPrices[$property->id]->minimum_price,
                    'location' => $location,
                    'url' => route('properties.show', $property),
                    'rating' => $property->rating_average,
                    'review_count' => (int) $property->rating_count,
                ];
            })->filter()->values();
        return response()->json(['data' => $data]);
    }
}
