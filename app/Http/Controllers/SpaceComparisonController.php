<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpaceComparisonController extends Controller
{
    private const SESSION_KEY = 'space_comparison';
    private const MAX_SPACES = 3;

    public function index(Request $request): View
    {
        $ids = $this->spaceIds($request);
        $spaces = Space::publiclyListed()
            ->with(['property', 'amenities', 'images'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Space $space) => array_search($space->id, $ids, true))
            ->values();
        $visibleIds = $spaces->modelKeys();
        $request->session()->put(self::SESSION_KEY, $visibleIds);

        return view('spaces.compare', compact('spaces'));
    }

    public function store(Request $request, Space $space): RedirectResponse
    {
        $space = Space::publiclyListed()->whereKey($space->id)->firstOrFail();
        $ids = $this->spaceIds($request);

        if (in_array($space->id, $ids, true)) {
            return back()->with('status', 'This space is already in your comparison.');
        }

        if (count($ids) >= self::MAX_SPACES) {
            return back()->with('error', 'Compare up to three spaces at a time. Remove one before adding another.');
        }

        $ids[] = $space->id;
        $request->session()->put(self::SESSION_KEY, $ids);

        return back()->with('status', 'Space added to your comparison.');
    }

    public function destroy(Request $request, Space $space): RedirectResponse
    {
        $ids = array_values(array_filter(
            $this->spaceIds($request),
            fn (int $id): bool => $id !== $space->id,
        ));
        $request->session()->put(self::SESSION_KEY, $ids);

        return back()->with('status', 'Space removed from your comparison.');
    }

    private function spaceIds(Request $request): array
    {
        $ids = $request->session()->get(self::SESSION_KEY, []);

        if (! is_array($ids)) {
            return [];
        }

        return array_slice(array_values(array_unique(array_filter(
            $ids,
            fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0,
        ))), 0, self::MAX_SPACES);
    }
}
