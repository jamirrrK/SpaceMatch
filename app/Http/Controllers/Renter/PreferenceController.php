<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('renter.preferences.edit', [
            'preferences' => $request->user()->renterPreference,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_or_campus' => ['nullable', 'string', 'max:150'],
            'move_in_date' => ['nullable', 'date', 'after_or_equal:today'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($request->filled('budget_min'), 'gte:budget_min')],
            'preferred_location' => ['nullable', 'string', 'max:150'],
            'space_type' => ['nullable', Rule::in(array_keys(config('property.types', [])))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'amenities_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->renterPreference()->updateOrCreate([], $validated);

        return redirect()->route('renter.preferences.edit')->with('status', 'Housing preferences saved.');
    }
}
