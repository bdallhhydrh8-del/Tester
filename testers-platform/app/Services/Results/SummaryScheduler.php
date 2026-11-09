<?php

namespace App\Services\Results;

use App\Jobs\GenerateProjectSummary;

class SummaryScheduler
{
    public function schedule(int $projectId): void
    {
        GenerateProjectSummary::dispatch($projectId)
            ->delay(now()->addSeconds(config('results.summary_delay_seconds', 60)));
    }
}