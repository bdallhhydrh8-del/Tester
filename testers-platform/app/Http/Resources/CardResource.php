<?php

namespace App\Http\Resources;

use App\Services\CardFlowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A card as a tester sees it. Only fields a tester needs, never admin-only ones.
 */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->resource->getAttribute('title'), // null until Dev 2 adds the column
            'instructions' => $this->instructions,
            'ask_success' => (bool) $this->ask_success,
            'ask_difficulty' => (bool) $this->ask_difficulty,
            'ask_notes' => (bool) $this->ask_notes,
            'ask_file' => (bool) $this->ask_file,
            'required' => (bool) $this->required,
            'custom_questions' => app(CardFlowService::class)->customQuestions($this->resource),
        ];
    }
}