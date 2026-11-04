<?php

namespace App\Http\Requests\Tester;

use App\Models\Card;
use App\Services\CardFlowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
    public function authorize(): bool
    {
        return true; // access is checked by the tester.project:expect-card middleware
    }

    public function rules(): array
    {
        /** @var Card $card */
        $card = $this->attributes->get('card'); // set by tester.project:expect-card

        return [
            'difficulty' => $card->ask_difficulty
                ? ['required', 'integer', 'between:1,5']
                : ['prohibited'],

            'notes' => $card->ask_notes
                ? ['nullable', 'string', 'max:2000']
                : ['prohibited'],

            // Only the shape is checked here. Ownership and "not already attached" are
            // checked by FileAttachmentService, so ids cannot be probed with exists:files,id.
            'file_ids' => $card->ask_file ? ['nullable', 'array', 'max:5'] : ['prohibited'],
            'file_ids.*' => ['integer', 'distinct'],
        ] + $this->customAnswerRules($card);
    }

    public function messages(): array
    {
        return [
            'difficulty.required' => __('tester.difficulty_invalid'),
            'difficulty.integer' => __('tester.difficulty_invalid'),
            'difficulty.between' => __('tester.difficulty_invalid'),
            'notes.max' => __('tester.notes_too_long'),
            'custom_answers.*.required' => __('tester.answer_required'),
            'custom_answers.*.min' => __('tester.answer_required'),
            'custom_answers.*.in' => __('tester.answer_not_allowed'),
        ];
    }

    /**
     * One rule per custom question, keyed by the question id.
     * There is deliberately no rule on "custom_answers" itself: validated() then returns only
     * the answers to questions this card really has, and drops any other key.
     */
    private function customAnswerRules(Card $card): array
    {
        $rules = [];

        foreach (app(CardFlowService::class)->customQuestions($card) as $question) {
            $id = $question['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $key = 'custom_answers.'.$id;
            $required = ! empty($question['required']);
            $options = array_values($question['options'] ?? []);

            switch ($question['type'] ?? null) {
                case 'text':
                    $rules[$key] = [$required ? 'required' : 'nullable', 'string', 'max:2000'];
                    break;

                case 'radio': // one option
                    $rules[$key] = [$required ? 'required' : 'nullable', 'string', Rule::in($options)];
                    break;

                case 'choice': // several options
                    $rules[$key] = $required ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
                    $rules[$key.'.*'] = ['string', 'distinct', Rule::in($options)];
                    break;
            }
        }

        return $rules;
    }
}