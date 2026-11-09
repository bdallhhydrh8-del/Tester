<?php

namespace App\Services\Results;

use Illuminate\Support\Collection;

class StatsCalculator
{
    /**
     * @param  array<int, array{duration_seconds: ?int, success: ?bool, difficulty: ?int}>  $answers  submitted answers only
     */
    public function taskStats(array $answers, int $idleCapSeconds): array
    {
        $rows = collect($answers);
        $count = $rows->count();
        $timed = $rows->filter(fn ($r) => $r['duration_seconds'] !== null);
        $times = $timed->pluck('duration_seconds')->filter(fn ($s) => $s <= $idleCapSeconds)->sort()->values();

        return [
            'answers' => $count,
            'idle_flagged' => $timed->count() - $times->count(),
            'avg_seconds' => $times->isEmpty() ? null : (int) round($times->avg()),
            'median_seconds' => $this->median($times),
            'success_rate' => $count ? (int) round($rows->where('success', true)->count() / $count * 100) : null,
            'avg_difficulty' => $count ? round($rows->avg('difficulty'), 1) : null,
        ];
    }

    private function median(Collection $sorted): ?float
    {
        $n = $sorted->count();
        if ($n === 0) {
            return null;
        }
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2;
    }
}