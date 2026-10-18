<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('renter.preferences.edit', [
            'amenities' => Amenity::orderBy('name')->get(),
            'preferences' => $request->user()->renterPreference,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_student' => ['sometimes', 'boolean'],
            'campus' => ['nullable', 'string', 'max:150'],
            'max_distance_km' => ['nullable', 'numeric', 'between:0,1000'],
            'preferred_types' => ['nullable', 'array', 'max:10'],
            'preferred_types.*' => ['string', 'distinct', Rule::in(array_keys(config('property.types', [])))],
            'preferred_amenity_ids' => ['nullable', 'array', 'max:50'],
            'preferred_amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
            'school_or_campus' => ['required_with:max_distance_km', Rule::requiredIf($request->boolean('is_student', $request->user()->is_student)), 'nullable', 'string', 'max:150'],
            'move_in_date' => ['nullable', 'date', 'after_or_equal:today'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($request->filled('budget_min'), 'gte:budget_min')],
            'preferred_location' => ['nullable', 'string', 'max:150'],
            'space_type' => ['nullable', Rule::in(array_keys(config('property.types', [])))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'amenities_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            if (array_key_exists('is_student', $validated)) {
                $request->user()->update(['is_student' => $validated['is_student']]);
            }
            unset($validated['is_student']);
            $validated['preferred_types'] = $validated['preferred_types'] ?? [];
            $validated['preferred_amenity_ids'] = $validated['preferred_amenity_ids'] ?? [];
            $request->user()->renterPreference()->updateOrCreate([], $validated);
        });

        return redirect()->route('renter.preferences.edit')->with('status', 'Housing preferences saved.');
    }
}
