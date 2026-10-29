<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'instructions' => $this->instructions,
            'ask_success' => $this->ask_success,
            'ask_difficulty' => $this->ask_difficulty,
            'ask_notes' => $this->ask_notes,
            'ask_file' => $this->ask_file,
            'required' => $this->required,
            'custom_questions' => $this->custom_questions,
        ];
    }
}