<?php

namespace Tests\Feature;

use App\Jobs\GenerateProjectSummary;
use App\Models\AiSummary;
use App\Services\Ai\SummaryGenerator;
use App\Services\Results\NotesPromptBuilder;
use App\Services\Results\ProjectNotesSource;
use App\Services\Results\SummaryScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateProjectSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeSource(array $notes): ProjectNotesSource
    {
        return new class($notes) implements ProjectNotesSource
        {
            public function __construct(private array $notes) {}

            public function notesFor(int $projectId): array
            {
                return $this->notes;
            }
        };
    }

    private function makeAi(): object
    {
        return new class implements SummaryGenerator
        {
            public int $calls = 0;

            public ?string $received = null;

            public function summarize(string $notes): string
            {
                $this->calls++;
                $this->received = $notes;

                return 'summary text';
            }
        };
    }

    private function runJob(int $project, array $notes, object $ai): void
    {
        (new GenerateProjectSummary($project))->handle($this->makeSource($notes), new NotesPromptBuilder, $ai);
    }

    public function test_five_finishes_in_a_row_queue_one_job_per_project(): void
    {
        Queue::fake();
        $scheduler = new SummaryScheduler;

        foreach (range(1, 5) as $i) {
            $scheduler->schedule(1);
        }
        $scheduler->schedule(2);

        Queue::assertPushed(GenerateProjectSummary::class, 2);
        Queue::assertPushed(GenerateProjectSummary::class, fn ($job) => $job->projectId === 1);
        Queue::assertPushed(GenerateProjectSummary::class, fn ($job) => $job->projectId === 2);
    }

    public function test_it_saves_a_ready_summary(): void
    {
        $this->runJob(1, ['good app', 'slow login'], $this->makeAi());

        $row = AiSummary::where('project_id', 1)->first();
        $this->assertSame('ready', $row->status);
        $this->assertSame('summary text', $row->summary);
        $this->assertSame(2, $row->notes_count);
        $this->assertNotNull($row->generated_at);
    }

    public function test_the_ai_never_sees_contact_details(): void
    {
        $ai = $this->makeAi();
        $this->runJob(1, ['call me +967 700 000 000 or sara@example.com'], $ai);

        $this->assertStringNotContainsString('700 000', $ai->received);
        $this->assertStringNotContainsString('sara@', $ai->received);
    }

    public function test_no_notes_means_no_ai_call_and_no_row(): void
    {
        $ai = $this->makeAi();
        $this->runJob(1, ['', '   '], $ai);

        $this->assertSame(0, $ai->calls);
        $this->assertSame(0, AiSummary::count());
    }

    public function test_running_again_updates_the_same_row(): void
    {
        $this->runJob(1, ['one'], $this->makeAi());
        $this->runJob(1, ['one', 'two', 'three'], $this->makeAi());

        $this->assertSame(1, AiSummary::count());
        $this->assertSame(3, AiSummary::first()->notes_count);
    }

    public function test_failure_with_no_summary_marks_failed(): void
    {
        (new GenerateProjectSummary(1))->failed(new \RuntimeException('boom'));

        $this->assertSame('failed', AiSummary::where('project_id', 1)->first()->status);
    }

    public function test_failure_keeps_an_existing_summary_visible(): void
    {
        $this->runJob(1, ['one'], $this->makeAi());
        (new GenerateProjectSummary(1))->failed(new \RuntimeException('boom'));

        $row = AiSummary::first();
        $this->assertSame('ready', $row->status);
        $this->assertSame('summary text', $row->summary);
    }
}