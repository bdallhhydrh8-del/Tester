<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_ids' => 'sometimes|array|max:5000',
            'selected_ids.*' => 'integer',
        ];
    }

    /** Empty means the whole project. */
    public function ids(): array
    {
        return array_map('intval', $this->validated('selected_ids', []));
    }
}
