<?php

namespace App\Services\Ai;

interface SummaryGenerator
{
    /** @param string $notes already cleaned by NotesPromptBuilder */
    public function summarize(string $notes): string;
}