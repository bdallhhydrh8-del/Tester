<?php

namespace App\Http\Requests;

use App\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Status is not here: it changes only through POST /projects/{id}/status
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(ProjectType::class)],
            'link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}