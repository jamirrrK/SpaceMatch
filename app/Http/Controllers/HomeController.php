<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        $featuredSpaces = \App\Models\Space::publiclyListed()
            ->with([
                'property.images' => fn ($images) => $images->whereNull('space_id'),
                'images', 'amenities',
            ])->latest()->take(3)->get();

        $heroSpaces = \App\Models\Space::publiclyListed()
            ->where(fn ($query) => $query->whereHas('images')
                ->orWhereHas('property.images', fn ($images) => $images->whereNull('space_id')))
            ->with([
                'property.images' => fn ($images) => $images->whereNull('space_id'),
                'images',
            ])
            ->latest()
            ->take(5)
            ->get();

        return view('home', compact('featuredSpaces', 'heroSpaces'));
    }
}
