<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') && (! $this->route('space') || $this->user()->can('update', $this->route('space')));
    }

    public function rules(): array
    {
        $propertyRules = ['required', 'integer', Rule::exists('properties', 'id')->where('owner_id', $this->user()->id)];
        if ($space = $this->route('space')) {
            // Reparenting would invalidate image ownership and existing booking context.
            $propertyRules[] = Rule::in([$space->property_id]);
        }

        return [
            'student_friendly' => ['sometimes', 'boolean'],
            'nearby_school' => ['required_with:campus_distance_km', 'nullable', 'string', 'max:150'],
            'campus_distance_km' => ['nullable', 'numeric', 'between:0,1000'],
            'furnishing' => ['nullable', Rule::in(['unfurnished', 'semi_furnished', 'furnished'])],
            'utilities_notes' => ['nullable', 'string', 'max:2000'],
            'transport_notes' => ['nullable', 'string', 'max:2000'],
            'property_id' => $propertyRules,
            'space_name' => ['required', 'string', 'max:255'],
            'space_type' => ['required', Rule::in(array_keys(config('property.types')))],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'capacity' => ['required', 'integer', 'between:1,10000'],
            'floor_area' => ['nullable', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'floor_number' => ['nullable', 'integer', 'between:-20,200'],
            'bedrooms' => ['nullable', 'integer', 'between:0,50'],
            'bathrooms' => ['nullable', 'integer', 'between:0,50'],
            'available_from' => ['nullable', 'date'],
            'deposit_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'advance_months' => ['nullable', 'integer', 'between:0,12'],
            'house_rules' => ['nullable', 'string', 'max:5000'],
            'move_in_requirements' => ['nullable', 'string', 'max:5000'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'availability_status' => ['required', Rule::in(['available', 'occupied', 'unavailable'])],
            'listing_status' => ['sometimes', Rule::in(['draft', 'published'])],
            'amenity_ids' => ['nullable', 'array', 'max:50'],
            'amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
        ];
    }
}
