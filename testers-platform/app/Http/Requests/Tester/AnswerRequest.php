<?php

namespace App\Http\Requests\Tester;

use Illuminate\Foundation\Http\FormRequest;


/**
 * POST /tester/projects/{project}/cards/{card}/donetask
 *
 * The tester answers "Did you complete the task successfully?" when pressing Done.
 * A card that does not ask for success takes no body at all.
 * The same request is sent again if the tester changes the answer before submitting.
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