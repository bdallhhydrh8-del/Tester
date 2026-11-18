<?php

namespace Tests\Unit;

use App\Services\Results\DashboardBuilder;
use PHPUnit\Framework\TestCase;

class DashboardBuilderTest extends TestCase
{
    public function test_empty_project_has_zero_rates(): void
    {
        $r = (new DashboardBuilder)->build([]);
        $this->assertSame(0, $r['started']);
        $this->assertSame(0, $r['completion_rate']);
        $this->assertNull($r['slowest_task']);
    }

    public function test_not_started_testers_are_not_counted(): void
    {
        $r = (new DashboardBuilder)->build([
            ['status' => 'not_started', 'device_type' => 'phone'],
            ['status' => 'in_progress', 'device_type' => 'phone'],
            ['status' => 'finished',    'device_type' => 'desktop'],
        ]);
        $this->assertSame(2, $r['started']);
        $this->assertSame(1, $r['finished']);
        $this->assertSame(50, $r['completion_rate']);
    }

    public function test_device_split_groups_missing_device_as_unknown(): void
    {
        $r = (new DashboardBuilder)->build([
            ['status' => 'finished', 'device_type' => 'phone'],
            ['status' => 'finished', 'device_type' => 'phone'],
            ['status' => 'in_progress'],
        ]);
        $this->assertSame(['phone' => 2, 'unknown' => 1], $r['device_split']);
    }

    public function test_slowest_task_ignores_cards_without_times(): void
    {
        $r = (new DashboardBuilder)->build([], [
            ['position' => 1, 'avg_seconds' => 40],
            ['position' => 2, 'avg_seconds' => null],
            ['position' => 3, 'avg_seconds' => 200],
        ]);
        $this->assertSame(3, $r['slowest_task']['position']);
    }
}