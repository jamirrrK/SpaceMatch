<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('property')
            ? $this->user()->can('update', $this->route('property'))
            : $this->user()->can('create', Property::class);
    }

    public function rules(): array
    {
        return [
            'region_code' => ['nullable', 'regex:/^\d{10}$/'],
            'province_code' => ['nullable', 'regex:/^\d{10}$/'],
            'city_code' => ['nullable', 'regex:/^\d{10}$/'],
            'barangay_code' => ['nullable', 'regex:/^\d{10}$/'],
            'location_visibility' => ['sometimes', Rule::in(['approximate', 'exact', 'hidden'])],
            'location_confirmed' => ['sometimes', 'boolean'],
            'property_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'property_type' => ['required', Rule::in(array_keys(config('property.types')))],
            'address' => ['required', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $locations = app(\App\Services\PhilippineLocations::class);
        $city = $locations->find($this->input('city_code'));
        if ($city) {
            $ancestors = $locations->ancestors($city['code']);
            $region = collect($ancestors)->firstWhere('type', 'region');
            $province = collect($ancestors)->firstWhere('type', 'province');
            $this->merge(['city' => $city['name'], 'province' => $province['name'] ?? $region['name'],
                'region_code' => $region['code'], 'province_code' => $province['code'] ?? null]);
        }
        if ($barangay = $locations->find($this->input('barangay_code'))) $this->merge(['barangay' => $barangay['name']]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function () {
            app(\App\Services\PhilippineLocations::class)->validateSelection($this->only(['region_code', 'province_code', 'city_code', 'barangay_code']));
        });
    }
}
