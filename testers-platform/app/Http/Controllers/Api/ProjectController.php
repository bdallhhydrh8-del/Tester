<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use RespondsWithErrors;

    // GET /projects?status=live&page=1&per_page=15 
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $projects = Project::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects)->additional(['message' => 'ok']);
    }

    // POST /projects: starts as disabled and copies the default cards 
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = DB::transaction(function () use ($request) {
            $project = Project::create([
                ...$request->validated(),
                'status' => ProjectStatus::Disabled,
                'created_by' => $request->user()->id,
            ]);

            // Each project gets its own copy. Changing the default later never touches it.
            foreach (Setting::current()->default_cards as $i => $card) {
                $project->cards()->create([
                    ...Arr::only($card, [
                        'instructions',
                        'ask_success',
                        'ask_difficulty',
                        'ask_notes',
                        'ask_file',
                        'required',
                        'custom_questions',
                    ]),
                    'position' => $i + 1,
                ]);
            }

            return $project;
        });

        return (new ProjectResource($project->load('cards')))
            ->additional(['message' => 'Project created'])
            ->response()
            ->setStatusCode(201);
    }

    // GET /projects/{project} 
    public function show(Project $project): ProjectResource
    {
        return (new ProjectResource($project->load('cards')))
            ->additional(['message' => 'ok']);
    }

    // PATCH /projects/{project}: name, type, link, note 
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return (new ProjectResource($project->fresh()->load('cards')))
            ->additional(['message' => 'Project updated']);
    }

    // POST /projects/{project}/status  body: { "status": "live" } 
    public function updateStatus(Request $request, Project $project): JsonResponse|ProjectResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
        ]);

        $next = ProjectStatus::from($data['status']);

        // Allowed: disabled to live, live to done, done to live (FR-P3). Anything else is 409.
        if (! $project->status->canMoveTo($next)) {
            return $this->fail(
                "A {$project->status->value} project cannot become {$next->value}",
                409
            );
        }

        $project->update(['status' => $next]);

        return (new ProjectResource($project->fresh()))
            ->additional(['message' => 'Status updated']);
    }
}