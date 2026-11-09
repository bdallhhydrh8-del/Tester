<?php

namespace App\Services\Results;

interface ProjectNotesSource
{
    /** @return string[] submitted notes for one project */
    public function notesFor(int $projectId): array;
}