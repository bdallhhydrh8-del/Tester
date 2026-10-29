<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CardRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDefaultCardsRequest extends FormRequest
{
    use CardRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_cards' => ['required', 'array', 'min:1', 'max:30'],
            'default_cards.*.instructions' => ['required', 'string', 'max:2000'],
            'default_cards.*.ask_success' => ['sometimes', 'boolean'],
            'default_cards.*.ask_difficulty' => ['sometimes', 'boolean'],
            'default_cards.*.ask_notes' => ['sometimes', 'boolean'],
            'default_cards.*.ask_file' => ['sometimes', 'boolean'],
            'default_cards.*.required' => ['sometimes', 'boolean'],
            ...$this->customQuestionRules('default_cards.*.'),
        ];
    }
}