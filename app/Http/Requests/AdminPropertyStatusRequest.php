<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminPropertyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('moderate', $this->route('property'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
