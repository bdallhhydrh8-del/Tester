<?php

namespace App\Services\Ai;

class FakeSummaryGenerator implements SummaryGenerator
{
    public function summarize(string $notes): string
    {
        $count = substr_count($notes, "\n") + 1;

        return "Placeholder summary of {$count} notes.";
    }
}