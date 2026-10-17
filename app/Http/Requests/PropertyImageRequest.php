<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('property'));
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=12000,max_height=12000'],
            'space_id' => ['nullable', 'integer', Rule::exists('spaces', 'id')->where('property_id', $this->route('property')->id)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $totalBytes = array_sum(array_map(fn ($file) => $file->getSize(), $this->file('images', [])));
            if ($totalBytes > 30 * 1024 * 1024) {
                $validator->errors()->add('images', 'The combined image size must not exceed 30 MB per upload.');
            }
        });
    }
}
