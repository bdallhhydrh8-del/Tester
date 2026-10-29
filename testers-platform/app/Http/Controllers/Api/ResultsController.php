<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TesterResultResource;
use App\Models\{Project, User};
use App\Services\Results\ResultsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function __construct(private ResultsService $results) {}

    // GET /projects/{project}/results/tasks
    public function tasks(Request $r, Project $project)
    {
        return ApiResponse::ok($this->results->taskStats($project, $this->filters($r)));
    }

    // GET /projects/{project}/results/testers
    public function testers(Request $r, Project $project)
    {
        $page = $this->results->accesses($project, $this->filters($r))
            ->with('tester:id,tester_number')                       // never name or phone
            ->withCount(['responses as answered_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->orderByDesc('started_at')
            ->paginate($r->integer('per_page', 20));

        return ApiResponse::page($page, TesterResultResource::class);
    }

    // GET /projects/{project}/results/testers/{tester}
    public function tester(Project $project, User $tester)
    {
        $access = $this->results->accesses($project)
            ->where('tester_id', $tester->id)
            ->with('tester:id,tester_number')
            ->firstOrFail();                                        // 404 if no access to this project

        return ApiResponse::ok($this->results->testerDetail($access));
    }

    private function filters(Request $r): array
    {
        return $r->validate([
            'device_type'   => 'sometimes|string|max:20',
            'tester_status' => 'sometimes|in:not_started,in_progress,finished',
            'invitation_id' => 'sometimes|integer',
            'per_page'      => 'sometimes|integer|min:1|max:100',
        ]);
    }
}
