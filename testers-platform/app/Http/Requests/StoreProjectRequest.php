<?php

namespace App\Http\Requests;

use App\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The role:admin middleware on the route does the real check
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'link' => ['nullable', 'url', 'max:2048'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}