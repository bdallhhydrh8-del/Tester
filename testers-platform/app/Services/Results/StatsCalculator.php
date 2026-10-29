<?php

namespace App\Services\Results;

use Illuminate\Support\Collection;

class StatsCalculator
{
    /** @param array<int, array{duration_seconds:int, success:bool, difficulty:int}> $answers */
    public function taskStats(array $answers, int $idleCapSeconds): array
    {
        $rows  = collect($answers);
        $count = $rows->count();
        $times = $rows->pluck('duration_seconds')->filter(fn ($s) => $s <= $idleCapSeconds)->sort()->values();

        return [
            'answers' => $count,
            'idle_flagged' => $count - $times->count(),
            'avg_seconds' => $times->isEmpty() ? null : (int) round($times->avg()),
            'median_seconds' => $this->median($times),
            'success_rate' => $count ? (int) round($rows->where('success', true)->count() / $count * 100) : null,
            'avg_difficulty' => $count ? round($rows->avg('difficulty'), 1) : null,
        ];
    }

    public function completionRate(int $started, int $finished): int
    {
        return $started ? (int) round($finished / $started * 100) : 0;
    }

    private function median(Collection $sorted): ?float
    {
        $n = $sorted->count();
        if ($n === 0) return null;
        $mid = intdiv($n, 2);
        return $n % 2 ? (float) $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2;
    }

        // tests/Unit/StatsCalculatorTest.php  (extends PHPUnit\Framework\TestCase, no database)
    public function test_idle_answers_are_left_out_of_time_averages(): void
    {
        $stats = (new StatsCalculator)->taskStats([
            ['duration_seconds' => 60,   'success' => true,  'difficulty' => 2],
            ['duration_seconds' => 120,  'success' => false, 'difficulty' => 4],
            ['duration_seconds' => 4000, 'success' => true,  'difficulty' => 3],
        ], 1800);

        $this->assertSame(1, $stats['idle_flagged']);
        $this->assertSame(90, $stats['avg_seconds']);
        $this->assertSame(90.0, $stats['median_seconds']);
        $this->assertSame(67, $stats['success_rate']);
    }
}