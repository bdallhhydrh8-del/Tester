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
            'title' => $this->title,
            'instructions' => $this->instructions,
            'ask_ease' => $this->ask_ease,
            'ask_notes' => $this->ask_notes,
            'ask_file' => $this->ask_file,
            'required' => $this->required,
            'custom_questions' => $this->custom_questions,
        ];
    }
}