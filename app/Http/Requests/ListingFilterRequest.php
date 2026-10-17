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
            'q' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'property_type' => ['nullable', 'string', Rule::in(array_keys(config('property.types')))],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'amenities' => ['nullable', 'array', 'max:50'],
            'amenities.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
