<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index(\App\Services\PropertyImageService $images)
    {
        $featuredSpaces = \App\Models\Space::publiclyListed()
            ->with([
                'property.images' => fn ($images) => $images->whereNull('space_id'),
                'images', 'amenities', 'property' => fn ($property) => $property->withReviewSummary(),
            ])->latest()->take(3)->get();

        $heroSpaces = \App\Models\Space::publiclyListed()
            ->with([
                'property.images' => fn ($images) => $images->whereNull('space_id'),
                'images',
            ])
            ->latest()
            ->take(5)
            ->get();

        $heroMedia = $heroSpaces->mapWithKeys(function ($space) use ($images) {
            $types = ['bedspace', 'dormitory', 'apartment', 'private_room', 'shared_room'];
            $fallback = 'category_'.(in_array($space->space_type, $types, true) ? $space->space_type : 'private_room');
            if ($space->space_type === 'private_room') {
                $fallback = 'featured_bedroom';
            }
            foreach ($space->images->concat($space->property->images) as $image) {
                if ($file = $images->readableFile($image)) {
                    return [$space->id => ['url' => $image->url, 'fallback' => $fallback, 'file' => $file]];
                }
            }

            return [$space->id => ['url' => null, 'fallback' => $fallback, 'file' => null]];
        });

        $heroSpaces = $heroSpaces->filter(fn ($space) => $heroMedia[$space->id]['url'] !== null)->values();

        return view('home', compact('featuredSpaces', 'heroSpaces', 'heroMedia'));
    }
}
