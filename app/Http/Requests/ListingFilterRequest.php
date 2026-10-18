<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_friendly' => ['nullable', 'boolean'],
            'campus' => ['nullable', 'string', 'max:150'],
            'transport' => ['nullable', 'string', 'max:150'],
            'school' => ['required_with:max_distance_km', 'nullable', 'string', 'max:150'],
            'max_distance_km' => ['nullable', 'numeric', 'between:0,1000'],
            'space_types' => ['nullable', 'array', 'max:10'],
            'space_types.*' => ['string', 'distinct', Rule::in(array_keys(config('property.types')))],
            'furnishing' => ['nullable', Rule::in(['unfurnished', 'semi_furnished', 'furnished'])],
            'q' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'property_type' => ['nullable', 'string', Rule::in(array_keys(config('property.types')))],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'available_from' => ['nullable', 'date', 'after_or_equal:today'],
            'amenities' => ['nullable', 'array', 'max:50'],
            'amenities.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'updated', 'relevant'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
