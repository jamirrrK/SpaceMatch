<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingFilterRequest;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;

class SpaceController extends Controller
{
    public function index(ListingFilterRequest $request): RedirectResponse
    {
        return redirect()->route('properties.index', $request->validated());
    }

    public function show(Space $space): RedirectResponse
    {
        $space = Space::publiclyListed()->whereKey($space->id)->firstOrFail();

        return redirect()->to(route('properties.show', $space->property_id).'#space-'.$space->id);
    }
}
