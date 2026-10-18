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
            'region_code' => ['nullable', 'regex:/^\d{10}$/'],
            'province_code' => ['nullable', 'regex:/^\d{10}$/'],
            'city_code' => ['nullable', 'regex:/^\d{10}$/'],
            'barangay_code' => ['nullable', 'regex:/^\d{10}$/'],
            'near_lat' => ['nullable', 'numeric', 'between:4,22', 'required_with:near_lng'],
            'near_lng' => ['nullable', 'numeric', 'between:116,127', 'required_with:near_lat'],
            'radius_km' => ['nullable', 'numeric', 'between:1,200'],
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

    public function withValidator($validator): void
    {
        $validator->after(fn () => app(\App\Services\PhilippineLocations::class)->validateSelection($this->only(['region_code', 'province_code', 'city_code', 'barangay_code'])));
    }
}
