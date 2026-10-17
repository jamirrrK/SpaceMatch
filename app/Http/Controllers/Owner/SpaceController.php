<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpaceRequest;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SpaceController extends Controller
{
    public function index(Request $request, ?Property $property = null)
    {
        if ($property) {
            Gate::authorize('update', $property);
        }
        $filters = $request->validate(['property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('owner_id', $request->user()->id)]]);
        $selectedPropertyId = $property?->id ?? ($filters['property_id'] ?? null);
        $properties = $request->user()->properties()->orderBy('property_name')->get();
        $spaces = Space::whereHas('property', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->when($selectedPropertyId, fn ($query) => $query->where('property_id', $selectedPropertyId))
            ->with(['property.images', 'images', 'amenities'])->latest()->paginate(12)->withQueryString();

        return view('owner.spaces.index', compact('spaces', 'properties', 'selectedPropertyId'));
    }

    public function create(Request $request)
    {
        $data = $request->validate(['property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('owner_id', $request->user()->id)]]);

        return $this->form($request, new Space(['property_id' => $data['property_id'] ?? null, 'availability_status' => 'available', 'listing_status' => 'draft', 'capacity' => 1]), 'create');
    }

    public function store(SpaceRequest $request)
    {
        $space = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $property = Property::whereKey($data['property_id'])->where('owner_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $this->validatePublication($property, $data['listing_status'] ?? 'draft');
            $space = $property->spaces()->create(Arr::except($data, ['amenity_ids', 'property_id']) + ['listing_status' => 'draft']);
            $space->amenities()->sync($data['amenity_ids'] ?? []);

            return $space;
        });

        return redirect()->route('owner.properties.show', $space->property_id)->with('status', 'Rental space created.');
    }

    public function edit(Request $request, Space $space)
    {
        Gate::authorize('update', $space);
        $space->load('amenities');

        return $this->form($request, $space, 'edit');
    }

    public function update(SpaceRequest $request, Space $space)
    {
        DB::transaction(function () use ($request, $space) {
            $data = $request->validated();
            $property = Property::whereKey($space->property_id)->lockForUpdate()->firstOrFail();
            $this->validatePublication($property, $data['listing_status'] ?? $space->listing_status);
            $space->update(Arr::except($data, ['amenity_ids', 'property_id']));
            $space->amenities()->sync($data['amenity_ids'] ?? []);
        });

        return redirect()->route('owner.properties.show', $space->property_id)->with('status', 'Rental space updated.');
    }

    public function publish(Request $request, Space $space)
    {
        Gate::authorize('update', $space);
        $data = $request->validate(['listing_status' => ['required', Rule::in(['draft', 'published'])]]);
        DB::transaction(function () use ($space, $data) {
            $property = Property::whereKey($space->property_id)->lockForUpdate()->firstOrFail();
            $this->validatePublication($property, $data['listing_status']);
            $space->update($data);
        });

        return back()->with('status', 'Space publication updated.');
    }

    private function form(Request $request, Space $space, string $mode)
    {
        return view('owner.spaces.'.$mode, [
            'space' => $space,
            'properties' => $request->user()->properties()->orderBy('property_name')->get(),
            'amenities' => Amenity::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    private function validatePublication(Property $property, string $status): void
    {
        if ($status === 'published' && ($property->moderated_at || ! in_array($property->status, ['active', 'available'], true))) {
            throw ValidationException::withMessages(['listing_status' => 'Spaces can only be published under an active property that has not been deactivated by an administrator.']);
        }
    }
}
