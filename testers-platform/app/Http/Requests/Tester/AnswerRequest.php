<?php

namespace App\Http\Requests\Tester;

use Illuminate\Foundation\Http\FormRequest;


/**
 * POST /tester/projects/{project}/cards/{card}/answer
 *
 * The tester answers "Did you complete the task successfully?" with Yes or No.
 * The question is asked on every task, so `success` is always required.
 * The answer stops the timer and is final: sending it again gives 409 already_answered.
 * Yes leads to the feedback page (submit). No leads to the problem page (problem).
 */
class AnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access is checked by the tester.project:expect-card middleware
    }

    public function rules(): array
    {  
        return ['success' => ['required', 'boolean']];

    }

    public function messages(): array
    {
        return [
            'success.required' => __('tester.success_missing'),
            'success.boolean' => __('tester.success_missing'),
        ];
    }
}