<?php

namespace App\Services;

use App\Models\Card;
use App\Models\ProjectAccess;
use App\Models\TaskResponse;
use Illuminate\Support\Collection;

/**
 * "2 of 5 tasks done" for a tester's projects, in two queries however many projects there are.
 */
class ProjectProgressService
{
    /**
     * @param  Collection<int, ProjectAccess>  $accesses
     * @return array<int, array{completed: int, total: int}>  keyed by access id
     */
    public function forAccesses(Collection $accesses): array
    {
        if ($accesses->isEmpty()) {
            return [];
        }

        // Deleted cards are not counted (soft delete scope).
        $totals = Card::query()
            ->whereIn('project_id', $accesses->pluck('project_id')->unique())
            ->selectRaw('project_id, COUNT(*) AS total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        // An answer to a card that was deleted later must not push "completed" above "total".
        $completed = TaskResponse::query()
            ->whereIn('project_access_id', $accesses->pluck('id'))
            ->whereNotNull('submitted_at')
            ->whereIn('card_id', Card::query()->select('id'))
            ->selectRaw('project_access_id, COUNT(*) AS completed')
            ->groupBy('project_access_id')
            ->pluck('completed', 'project_access_id');

        $progress = [];

        foreach ($accesses as $access) {
            $progress[$access->id] = [
                'completed' => (int) ($completed[$access->id] ?? 0),
                'total' => (int) ($totals[$access->project_id] ?? 0),
            ];
        }

        return $progress;
    }
}