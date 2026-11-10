<?php

namespace App\Services\Results;

use App\Models\TaskResponse;

class EloquentProjectNotesSource implements ProjectNotesSource
{
    public function notesFor(int $projectId): array
    {
        return TaskResponse::query()
            ->whereHas('projectAccess', fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('submitted_at')
            ->whereNotNull('notes')
            ->orderBy('id')
            ->pluck('notes')
            ->all();
    }
}