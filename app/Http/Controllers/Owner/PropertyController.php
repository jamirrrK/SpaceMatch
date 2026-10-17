<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyRequest;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $properties = $request->user()->properties()->withCount('spaces')->with('images')->latest()->paginate(12);

        return view('owner.properties.index', compact('properties'));
    }

    public function create()
    {
        Gate::authorize('create', Property::class);

        return view('owner.properties.create', ['property' => new Property]);
    }

    public function store(PropertyRequest $request)
    {
        $property = $request->user()->properties()->create($request->validated() + ['status' => 'active', 'listing_status' => 'draft']);

        return redirect()->route('owner.properties.show', $property)->with('status', 'Property created. Add rental spaces and photos, then publish your listing.');
    }

    public function show(Property $property)
    {
        Gate::authorize('update', $property);
        $property->load(['images.space', 'spaces.amenities', 'spaces.images']);

        return view('owner.properties.show', compact('property'));
    }

    public function edit(Property $property)
    {
        Gate::authorize('update', $property);
        $property->load(['images', 'spaces.amenities']);

        return view('owner.properties.edit', compact('property'));
    }

    public function update(PropertyRequest $request, Property $property)
    {
        $property->update($request->validated());

        return redirect()->route('owner.properties.show', $property)->with('status', 'Property information updated.');
    }

    public function status(Request $request, Property $property)
    {
        Gate::authorize('update', $property);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'archived'])]]);
        DB::transaction(function () use ($property, $data) {
            $locked = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            $this->assertNotModerated($locked);
            $locked->update($data);
        });

        return back()->with('status', 'Property status updated.');
    }

    public function publish(Request $request, Property $property)
    {
        Gate::authorize('update', $property);
        $data = $request->validate(['listing_status' => ['required', Rule::in(['draft', 'published'])]]);
        DB::transaction(function () use ($property, $data) {
            $locked = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            if ($data['listing_status'] === 'published') {
                $this->assertNotModerated($locked);
                if (! in_array($locked->status, ['active', 'available'], true) || ! $locked->spaces()->where('listing_status', 'published')->exists()) {
                    throw ValidationException::withMessages(['listing_status' => 'Activate the property and publish at least one rental space first.']);
                }
            }
            $locked->update($data);
        });

        return back()->with('status', 'Property publication updated.');
    }

    private function assertNotModerated(Property $property): void
    {
        if ($property->moderated_at || $property->status === 'inactive') {
            throw ValidationException::withMessages(['status' => 'An administrator has deactivated this property. Contact an administrator to restore it.']);
        }
    }
}
