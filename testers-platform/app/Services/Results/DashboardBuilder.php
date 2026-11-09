<?php

namespace App\Services\Results;

class DashboardBuilder
{
    /**
     * @param  array<int, array{status: string, device_type?: ?string}>  $accesses
     * @param  array<int, array{avg_seconds: ?int}>  $taskStats  rows from StatsCalculator
     */
    public function build(array $accesses, array $taskStats = []): array
    {
        $started  = collect($accesses)->where('status', '!=', 'not_started');
        $total    = $started->count();
        $finished = $started->where('status', 'finished')->count();

        return [
            'started'         => $total,
            'finished'        => $finished,
            'completion_rate' => $total ? (int) round($finished / $total * 100) : 0,
            'device_split'    => $started->groupBy(fn ($a) => $a['device_type'] ?? 'unknown')
                                         ->map->count()->all(),
            'slowest_task'    => $this->slowest($taskStats),
        ];
    }

    public function slowest(array $taskStats): ?array
    {
        return collect($taskStats)->whereNotNull('avg_seconds')->sortByDesc('avg_seconds')->first();
    }
}