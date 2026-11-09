<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the role:admin middleware already checks access
    }

    public function rules(): array
    {
        return [
            'device_type' => 'sometimes|string|max:20',
            'tester_status' => 'sometimes|in:not_started,in_progress,finished',
            'invitation_id' => 'sometimes|integer',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only(['device_type', 'tester_status', 'invitation_id']);
    }

    public function pageNumber(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', config('results.per_page', 20));
    }
}
