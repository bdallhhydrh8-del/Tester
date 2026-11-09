<?php

namespace App\Jobs;

use App\Models\AiSummary;
use App\Services\Ai\SummaryGenerator;
use App\Services\Results\NotesPromptBuilder;
use App\Services\Results\ProjectNotesSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class GenerateProjectSummary implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];   // seconds between attempts
    public int $timeout = 60;            // keep below the queue's retry_after (90)
    public int $uniqueFor = 120;         // longer than the scheduling delay

    public function __construct(public int $projectId) {}

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    public function handle(ProjectNotesSource $source, NotesPromptBuilder $prompt, SummaryGenerator $ai): void
    {
        $notes = $source->notesFor($this->projectId);
        $text = $prompt->build($notes);

        if ($text === null) {
            return;   // nothing to summarise: no AI call, no row, the endpoint keeps saying pending
        }

        AiSummary::updateOrCreate(
            ['project_id' => $this->projectId],
            [
                'summary' => $ai->summarize($text),
                'notes_count' => collect($notes)->filter(fn ($n) => trim((string) $n) !== '')->count(),
                'status' => 'ready',
                'generated_at' => now(),
            ]
        );
    }

    public function failed(Throwable $e): void
    {
        $row = AiSummary::firstOrNew(['project_id' => $this->projectId]);

        if (! $row->summary) {     // a failed refresh must not hide an existing good summary
            $row->status = 'failed';
            $row->save();
        }
    }
}