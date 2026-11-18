<?php

namespace App\Http\Resources\Tester;

use App\Models\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One project card on the home page. The wrapped resource is the tester's ProjectAccess.
 */
class HomeProjectResource extends JsonResource
{
    /** @param array{completed: int, total: int} $progress */
    public function __construct($resource, private array $progress)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $project = $this->resource->project;
        $type = $project->type;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'type' => $type instanceof \BackedEnum ? $type->value : $type,
            // The tester's own state: not_started | in_progress | finished
            'status' => $this->resource->status,
            'finished' => $this->resource->status === ProjectAccess::FINISHED,
            'progress' => $this->progress,
        ];
    }
}