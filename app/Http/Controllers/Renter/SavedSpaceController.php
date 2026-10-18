<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedSpaceController extends Controller
{
    public function index(Request $request): View
    {
        $spaces = $request->user()->savedSpaces()
            ->publiclyListed()
            ->withExists(['savedByUsers as is_saved' => fn ($users) => $users->whereKey($request->user()->id)])
            ->with(['property.images', 'images', 'amenities'])
            ->orderByPivot('created_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        return view('renter.saved-spaces.index', compact('spaces'));
    }

    public function store(Request $request, Space $space): RedirectResponse
    {
        $space = Space::publiclyListed()->whereKey($space->id)->firstOrFail();
        $request->user()->savedSpaces()->syncWithoutDetaching([$space->id]);

        return back()->with('status', 'Space saved to your list.');
    }

    public function destroy(Request $request, Space $space): RedirectResponse
    {
        $request->user()->savedSpaces()->detach($space->id);

        return back()->with('status', 'Space removed from your saved list.');
    }
}
