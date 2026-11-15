<?php

namespace App\Http\Requests\Tester;

use App\Models\TaskResponse;
use App\Services\CardFlowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;


/**
 * POST /tester/projects/{project}/cards/{card}/submit
 *
 * The rules come from the card: which questions it enables, and its custom questions.
 * Success is not here: it was saved when the tester pressed Done with task.
 *
 * Which fields are required (a decision to confirm with the company):
 *   difficulty  required when the card asks for it
 *   notes       optional
 *   file_ids    optional
 *   custom questions follow their own "required" flag
 */
class SubmitCardRequest extends FormRequest
{
    private ?array $definition = null;

    public function authorize(): bool { return true; }

    /** The card as the tester saw it: the saved copy once the task has started. */
    private function definition(): array
    {
        return $this->definition ??= app(CardFlowService::class)->definition(
            $this->attributes->get('card'),
            TaskResponse::query()
                ->where('project_access_id', $this->attributes->get('project_access')->id)
                ->where('card_id', $this->attributes->get('card')->id)
                ->first()
        );
    }

    public function rules(): array
    {
        $def = $this->definition();

        return [
            'ease' => ! empty($def['ask_ease']) ? ['required', 'integer', 'between:1,5'] : ['prohibited'],
            'notes' => ! empty($def['ask_notes']) ? ['nullable', 'string', 'max:250'] : ['prohibited'],
            'file_ids' => ! empty($def['ask_file']) ? ['nullable', 'array', 'max:5'] : ['prohibited'],
            'file_ids.*' => ['integer', 'distinct'],
        ] + $this->customAnswerRules($def['custom_questions'] ?? []);
    }

    /** An answer to a question the card does not have is an error, not silently dropped. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $known = array_column($this->definition()['custom_questions'] ?? [], 'id');

            foreach (array_keys((array) $this->input('custom_answers', [])) as $id) {
                if (! in_array($id, $known, true)) {
                    $v->errors()->add('custom_answers.'.$id, __('tester.answer_not_allowed'));
                }
            }
        });
    }

    private function customAnswerRules(array $questions): array
    {
        $rules = [];

        foreach ($questions as $question) {
            $id = $question['id'] ?? null;
            if (! is_string($id) || $id === '') continue;

            $key = 'custom_answers.'.$id;
            $required = ! empty($question['required']);
            $options = array_values($question['options'] ?? []);

            switch ($question['type'] ?? null) {
                case 'radio':   // one option
                    $rules[$key] = [$required ? 'required' : 'nullable', 'string', Rule::in($options)];
                    break;
                case 'checkbox': // several options; a required one needs at least one
                    $rules[$key] = $required ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
                    $rules[$key.'.*'] = ['string', 'distinct', Rule::in($options)];
                    break;
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'ease.required' => __('tester.ease_invalid'),
            'ease.integer' => __('tester.ease_invalid'),
            'ease.between' => __('tester.ease_invalid'),
            'notes.max' => __('tester.text_too_long'),
            'custom_answers.*.required' => __('tester.answer_required'),
            'custom_answers.*.min' => __('tester.answer_required'),
            'custom_answers.*.in' => __('tester.answer_not_allowed'),
        ];
    }
}