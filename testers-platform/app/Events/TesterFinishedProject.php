<?php

namespace App\Events;

use App\Models\ProjectAccess;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the last card is submitted and the access is marked finished.
 * Dev 4 listens to it (AI summary, admin overview). It is fired after the database
 * transaction has committed, so a listener always sees the saved answers.
 */
class TesterFinishedProject
{
    use Dispatchable, SerializesModels;

    public function __construct(public ProjectAccess $access)
    {
    }
}