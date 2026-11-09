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
        'entered_at',
        'browser',
        'os',
        'device_type',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'entered_at' => 'datetime',
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
    /** The tester's current attempt: the access from the link they opened last. Null counts as oldest. */
    public static function currentFor(int $testerId, int $projectId): ?self
    {
        return static::query()
            ->where('tester_id', $testerId)
            ->where('project_id', $projectId)
            ->orderByRaw('entered_at IS NULL')
            ->orderByDesc('entered_at')
            ->orderByDesc('id')
            ->first();
    }

    /** From many accesses, keep only the current attempt of each project. */
    public static function pickCurrent(\Illuminate\Support\Collection $accesses): \Illuminate\Support\Collection
    {
        return $accesses->groupBy('project_id')
            ->map(fn($group) => $group->sortByDesc(fn($a) => [$a->entered_at?->timestamp ?? 0, $a->id])->first())
            ->values();
    }
}
