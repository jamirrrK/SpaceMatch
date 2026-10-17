<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminPropertyFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Property::class);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_OWNER)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
