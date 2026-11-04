<?php

namespace Tests\Feature\Tester;

use App\Models\ProjectAccess;
use App\Models\TaskResponse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs only after Dev 2's projects and cards tables, models and factories are in main.
 */
class ProjectAccessSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_has_at_most_one_access_per_project(): void
    {
        $access = ProjectAccess::factory()->create();

        $this->expectException(QueryException::class);

        ProjectAccess::create([
            'tester_id' => $access->tester_id,
            'project_id' => $access->project_id,
        ]);
    }

    public function test_an_access_has_at_most_one_response_per_card(): void
    {
        $response = TaskResponse::factory()->create();

        $this->expectException(QueryException::class);

        TaskResponse::create([
            'project_access_id' => $response->project_access_id,
            'card_id' => $response->card_id,
        ]);
    }

    public function test_a_new_access_starts_as_not_started(): void
    {
        $access = ProjectAccess::factory()->create()->fresh();

        $this->assertSame(ProjectAccess::NOT_STARTED, $access->status);
        $this->assertNull($access->started_at);
        $this->assertNull($access->browser);
    }

    public function test_response_values_keep_their_types(): void
    {
        $response = TaskResponse::factory()->submitted(185)->create()->fresh();

        $this->assertSame(185, $response->duration_seconds);
        $this->assertIsArray($response->card_snapshot);
        $this->assertIsBool($response->success);
        $this->assertTrue($response->isSubmitted());
        // Start and end agree with the stored duration.
        $this->assertSame(
            185,
            (int) round($response->task_started_at->diffInSeconds($response->task_ended_at, true))
        );
    }

    public function test_an_idle_response_is_above_the_thirty_minute_cap(): void
    {
        $response = TaskResponse::factory()->idle()->create()->fresh();

        $this->assertGreaterThan(30 * 60, $response->duration_seconds);
    }
}