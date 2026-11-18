<?php

namespace App\Contracts;

use App\Models\Card;
use App\Models\ProjectAccess;
interface FailedTaskIssueCreator
{
    /**
     * Creates the issue for a failed task and attaches its screenshots.
     * Runs inside the caller's transaction, so a failure rolls everything back.
     */
    public function createFromFailedTask(
        ProjectAccess $access,
        Card $card,
        string $reason,
        ?string $description,
        array $fileIds,
    );
}