<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait CardRules
{
    
     // Rules for the checkbox and radio questions of one card.
     // $prefix is empty for a single card, or 'default_cards.*.' for the default form.
     
    protected function customQuestionRules(string $prefix = ''): array
    {
        return [
            $prefix.'custom_questions' => [
                'nullable', 'array', 'max:10',
                function ($attribute, $value, $fail) {
                    if (! is_array($value)) {
                        return;
                    }
                    $ids = collect($value)->pluck('id');
                    if ($ids->count() !== $ids->unique()->count()) {
                        $fail('Question ids must be different inside one card.');
                    }
                },
            ],
            $prefix.'custom_questions.*.id' => ['required', 'string', 'max:20'],
            $prefix.'custom_questions.*.type' => ['required', Rule::in(['checkbox', 'radio'])],
            $prefix.'custom_questions.*.label' => ['required', 'string', 'max:255'],
            $prefix.'custom_questions.*.options' => [
                'required', 'array', 'min:2', 'max:10',
                function ($attribute, $value, $fail) {
                    if (is_array($value) && count($value) !== count(array_unique($value))) {
                        $fail('Options must be different from each other.');
                    }
                },
            ],
            $prefix.'custom_questions.*.options.*' => ['required', 'string', 'max:100'],
            $prefix.'custom_questions.*.required' => ['boolean'],
        ];
    }
}