<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CardRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
{
    use CardRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // position is not here: order changes only through the reorder endpoint
        return [
            'instructions' => ['sometimes', 'required', 'string', 'max:2000'],
            'ask_success' => ['sometimes', 'boolean'],
            'ask_difficulty' => ['sometimes', 'boolean'],
            'ask_notes' => ['sometimes', 'boolean'],
            'ask_file' => ['sometimes', 'boolean'],
            'required' => ['sometimes', 'boolean'],
            ...$this->customQuestionRules(),
        ];
    }
}