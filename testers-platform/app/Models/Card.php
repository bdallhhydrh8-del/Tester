<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'position',
        'instructions',
        'ask_success',
        'ask_difficulty',
        'ask_notes',
        'ask_file',
        'required',
        'custom_questions',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'ask_success' => 'boolean',
            'ask_difficulty' => 'boolean',
            'ask_notes' => 'boolean',
            'ask_file' => 'boolean',
            'required' => 'boolean',
            'custom_questions' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    
    //The copy Dev 3 stores in responses.card_snapshot when a tester submits,
    //so editing the card later never changes old answers (FR-P11).
     
    public function toSnapshot(): array
    {
        return [
            'instructions' => $this->instructions,
            'ask_success' => $this->ask_success,
            'ask_difficulty' => $this->ask_difficulty,
            'ask_notes' => $this->ask_notes,
            'ask_file' => $this->ask_file,
            'required' => $this->required,
            'position' => $this->position,
            'custom_questions' => $this->custom_questions,
        ];
    }
}