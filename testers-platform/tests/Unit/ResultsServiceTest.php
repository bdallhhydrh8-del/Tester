<?php

namespace Tests\Unit;

use App\Services\Results\DashboardBuilder;
use App\Services\Results\FixtureResultsDataSource;
use App\Services\Results\ResultsService;
use App\Services\Results\StatsCalculator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

// Extends the Laravel TestCase only because abort(404) needs the app. No database is used.
class ResultsServiceTest extends TestCase
{
    private ResultsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResultsService(new FixtureResultsDataSource, new StatsCalculator, new DashboardBuilder);
    }

    public function test_task_stats_has_one_row_per_card_and_counts_reached_and_answers(): void
    {
        $tasks = $this->service->taskStats(1);

        $this->assertCount(4, $tasks);
        $this->assertSame([7, 7, 6, 4], array_column($tasks, 'reached'));
        $this->assertSame([7, 6, 4, 3], array_column($tasks, 'answers'));
    }

    public function test_idle_answer_is_flagged_and_left_out_of_the_averages(): void
    {
        $card2 = $this->service->taskStats(1)[1];

        $this->assertSame(1, $card2['idle_flagged']);
        $this->assertSame(100, $card2['avg_seconds']);
        $this->assertEquals(100, $card2['median_seconds']);
    }

    public function test_device_filter_narrows_the_task_stats(): void
    {
        // phone testers with answers on card 1: testers 1, 3, 4 and 6 (tester 7 never started)
        $phoneOnly = $this->service->taskStats(1, ['device_type' => 'phone']);

        $this->assertSame(4, $phoneOnly[0]['reached']);
    }

    public function test_dashboard_counts_started_finished_and_device_split(): void
    {
        $d = $this->service->dashboard(1);

        $this->assertSame('Shop app', $d['project']['name']);
        $this->assertSame(7, $d['started']);          // the not_started tester is left out
        $this->assertSame(3, $d['finished']);
        $this->assertSame(43, $d['completion_rate']);
        $this->assertSame(['phone' => 4, 'desktop' => 3], $d['device_split']);
    }

    public function test_tester_list_exposes_only_whitelisted_fields(): void
    {
        $list = $this->service->testers(1);

        $this->assertCount(8, $list);
        foreach ($list as $row) {
            $this->assertArrayNotHasKey('name', $row);
            $this->assertArrayNotHasKey('phone', $row);
            $this->assertArrayNotHasKey('invitation_id', $row);
        }
        $this->assertSame(4, $list[0]['answered_count']);
    }

    public function test_tester_detail_marks_the_idle_answer_and_orders_by_card(): void
    {
        $d = $this->service->testerDetail(1, 3);

        $this->assertSame([1, 2, 3, 4], array_column($d['answers'], 'position'));
        $this->assertSame([false, true, false, false], array_column($d['answers'], 'idle'));
    }

    public function test_tester_with_no_answers_yet_is_found_but_empty(): void
    {
        $this->assertSame([], $this->service->testerDetail(1, 7)['answers']);
    }

    public function test_unknown_tester_is_404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->service->testerDetail(1, 99);
    }

    public function test_unknown_project_is_404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->service->dashboard(99);
    }

    public function test_notes_only_include_submitted_answers_with_text(): void
    {
        $notes = $this->service->notes(1);

        $this->assertNotEmpty($notes);
        foreach ($notes as $n) {
            $this->assertNotSame('', trim($n['notes']));
            $this->assertSame(['id', 'tester_number', 'card_position', 'notes'], array_keys($n));
        }
        $this->assertContains('ممتاز', array_column($notes, 'notes'));
    }

    public function test_export_answers_respect_selected_ids(): void
    {
        $all = $this->service->exportAnswers(1);
        $firstId = $this->service->notes(1)[0]['id'];

        $this->assertCount(1, $this->service->exportAnswers(1, [$firstId]));
        $this->assertGreaterThan(1, count($all));
        $this->assertArrayNotHasKey('name', $all[0]);
    }

    public function test_overview_covers_live_projects_and_an_empty_project_does_not_break_it(): void
    {
        $o = $this->service->overview();

        $this->assertSame(2, $o['live_projects']);
        $this->assertSame(4, $o['active_testers']);
        $this->assertLessThanOrEqual(5, count($o['slowest_tasks']));
        $this->assertSame(1, $o['slowest_tasks'][0]['project_id']);
        $this->assertCount(3, $o['recent_activity']);
    }
}
