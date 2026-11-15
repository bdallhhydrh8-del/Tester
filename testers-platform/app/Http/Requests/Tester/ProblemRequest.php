<?php

namespace App\Http\Requests\Tester;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProblemRequest extends FormRequest
{
    public const REASONS = ['slow_connection', 'did_not_understand', 'unclear_interface', 'other'];

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(self::REASONS)],
            'description' => [Rule::requiredIf($this->input('reason') === 'other'), 'nullable', 'string', 'max:250'],
            'file_ids' => ['nullable', 'array', 'max:5'],
            'file_ids.*' => ['integer', 'distinct'],
            'action' => ['required', Rule::in(['continue', 'end'])],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('tester.reason_invalid'),
            'reason.in' => __('tester.reason_invalid'),
            'description.required' => __('tester.description_required'),
            'description.max' => __('tester.text_too_long'),
            'action.required' => __('tester.action_invalid'),
            'action.in' => __('tester.action_invalid'),
        ];
    }
}