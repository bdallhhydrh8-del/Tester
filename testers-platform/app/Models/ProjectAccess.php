<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tester's grant to one project. Rows are created by Dev 1's GrantService
 * (tester_id, project_id, invitation_id only); everything else is Dev 3's.
 */
class ProjectAccess extends Model
{
    use HasFactory;

    public const NOT_STARTED = 'not_started';
    public const IN_PROGRESS = 'in_progress';
    public const FINISHED = 'finished';

    protected $fillable = [
        'tester_id',
        'project_id',
        'invitation_id',
        'status',
        'started_at',
        'finished_at',
        'browser',
        'os',
        'device_type',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class); // Dev 2's model
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class); // Dev 1's model
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TaskResponse::class);
    }

    public function hasStarted(): bool
    {
        return $this->status !== self::NOT_STARTED;
    }

    public function isFinished(): bool
    {
        return $this->status === self::FINISHED;
    }
}