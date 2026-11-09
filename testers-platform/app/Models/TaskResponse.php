<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A tester's answer to one card. The table is `responses`; the class is named
 * TaskResponse because "Response" clashes with Laravel's HTTP Response class.
 */
class TaskResponse extends Model
{
    use HasFactory;

    protected $table = 'responses';

    protected $fillable = [
        'project_access_id',
        'card_id',
        'card_snapshot',
        'task_started_at',
        'task_ended_at',
        'duration_seconds',
        'success',
        'difficulty',
        'notes',
        'custom_answers',
        'submitted_at',
    ];

    protected $casts = [
        'card_snapshot' => 'array',
        'custom_answers' => 'array',
        'task_started_at' => 'datetime',
        'task_ended_at' => 'datetime',
        'submitted_at' => 'datetime',
        'duration_seconds' => 'integer',
        'success' => 'boolean',
        'difficulty' => 'integer',
    ];

    public function access(): BelongsTo
    {
        return $this->belongsTo(ProjectAccess::class, 'project_access_id');
    }

    public function card(): BelongsTo
    {
        // Cards are soft-deleted, and an old response must still reach its card.
        return $this->belongsTo(Card::class)->withTrashed(); // Dev 2's model
    }

    public function files(): MorphMany
    {
        return $this->morphMany(StoredFile::class, 'fileable');
    }

    public function hasStartedTask(): bool
    {
        return $this->task_started_at !== null;
    }

    public function hasEndedTask(): bool
    {
        return $this->task_ended_at !== null;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}