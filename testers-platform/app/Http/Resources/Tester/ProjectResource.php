<?php

namespace App\Http\Resources\Tester;

use App\Models\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The project page. The wrapped resource is the tester's ProjectAccess.
 * Only fields a tester needs: never the creator, status or other admin data.
 */
class ProjectResource extends JsonResource
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
            'note' => $project->note,
            // A website address for website projects, the install link for apps.
            // For a website, the page opens it in a new tab from a direct button click.
            'link' => $project->link,
            'status' => $this->resource->status,
            'started' => $this->resource->hasStarted(),
            'finished' => $this->resource->status === ProjectAccess::FINISHED,
            'progress' => $this->progress,
        ];
    }
}
