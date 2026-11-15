<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tester\HomeProjectResource;
use App\Http\Resources\Tester\ProjectResource;
use App\Models\ProjectAccess;
use App\Services\CardFlowService;
use App\Exceptions\TesterFlowException;
use App\Services\ProjectProgressService;
use App\Support\UserAgentParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The tester's home page and project page.
 * Project and card access is checked by the tester.project middleware, never here.
 */
class TesterProjectController extends Controller
{
    public function __construct(private ProjectProgressService $progress, private CardFlowService $flow) {}
    public function home(Request $request): JsonResponse
    {
        // One entry per project: the current attempt, live projects only.
        $accesses = ProjectAccess::pickCurrent(
            ProjectAccess::query()
                ->where('tester_id', $request->user()->id)
                ->with('project')
                ->get()
                ->filter(fn(ProjectAccess $a) => $a->project?->acceptsAnswers())
        );

        $progress = $this->progress->forAccesses($accesses);

        // The project the tester is in the middle of, if any, is not listed: they resume it.
        $resume = $accesses->firstWhere('status', ProjectAccess::IN_PROGRESS);

        $listed = $accesses
            ->reject(fn(ProjectAccess $a) => $a->status === ProjectAccess::IN_PROGRESS)
            ->sortByDesc('id')
            ->values();

        return $this->respond([
            'resume_project_id' => $resume?->project_id,
            'projects' => $listed
                ->map(fn(ProjectAccess $a) => (new HomeProjectResource($a, $progress[$a->id]))->resolve())
                ->all(),
        ]);
    }

    /** GET /tester/projects/{project} : note, link and the tester's progress. */
    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->projectPayload($request->attributes->get('project_access')));
    }

    /**
     * POST /tester/projects/{project}/start : the tester pressed "Start test".
     * Safe to call again: the first call wins, nothing is ever overwritten.
     */
    public function start(Request $request, UserAgentParser $agents): JsonResponse
    {
        /** @var ProjectAccess $access */
        $access = $request->attributes->get('project_access');

        // One project at a time: finish or end the current one first.
        if (
            $access->status === ProjectAccess::NOT_STARTED
            && $this->otherInProgress($request->user()->id, $access->project_id)
        ) {
            throw new TesterFlowException('another_project_in_progress');
        }

        // A finished project stays finished if the tester opens it again.
        if ($access->status === ProjectAccess::NOT_STARTED) {
            $access->status = ProjectAccess::IN_PROGRESS;
        }

        if ($access->started_at === null) {
            $access->started_at = now();
        }

        // Browser, OS and device are recorded once, from the first start (FR-T10).
        if ($access->browser === null && $access->os === null && $access->device_type === null) {
            $access->fill($agents->parse($request->userAgent()));
        }

        $access->save();

        return $this->respond($this->projectPayload($access));
    }

    private function projectPayload(ProjectAccess $access): array
    {
        $access->loadMissing('project');
        $progress = $this->progress->forAccesses(collect([$access]));

        $payload = (new ProjectResource($access, $progress[$access->id]))->resolve();

        // For a project in progress, the screen the tester must land on. Null otherwise.
        $payload['current'] = $access->status === ProjectAccess::IN_PROGRESS
            ? $this->flow->state($access)
            : null;

        return $payload;
    }

    /** True if the tester has another live project in progress (current attempts only). */
    private function otherInProgress(int $testerId, int $projectId): bool
    {
        $current = ProjectAccess::pickCurrent(
            ProjectAccess::query()
                ->where('tester_id', $testerId)
                ->where('project_id', '!=', $projectId)
                ->with('project')
                ->get()
                ->filter(fn(ProjectAccess $a) => $a->project?->acceptsAnswers())
        );

        return $current->contains(fn(ProjectAccess $a) => $a->status === ProjectAccess::IN_PROGRESS);
    }


    private function respond(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }
}
