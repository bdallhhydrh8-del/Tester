<?php

namespace App\Services; 

use App\Models\Card;
use App\Models\ProjectAccess;
use App\Contracts\FailedTaskIssueCreator;


class NullFailedTaskIssueCreator implements FailedTaskIssueCreator
{
    public function createFromFailedTask(ProjectAccess $access, Card $card, string $reason, ?string $description, array $fileIds)
    {
        // Placeholder until Dev 4's service exists. Screenshots are not attached yet.
    }
}