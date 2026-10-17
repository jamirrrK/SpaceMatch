<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', ['featuredSpaces' => \App\Models\Space::publiclyListed()
            ->with([
                'property.images' => fn ($images) => $images->whereNull('space_id'),
                'images', 'amenities',
            ])->latest()->take(3)->get()]);
    }
}
