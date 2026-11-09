<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tester\HomeProjectResource;
use App\Http\Resources\Tester\ProjectResource;
use App\Models\ProjectAccess;
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
    public function __construct(private ProjectProgressService $progress) {}

    /** GET /tester/home : the live projects this tester has access to, with progress. */
    public function home(Request $request): JsonResponse
    {
        $accesses = ProjectAccess::pickCurrent(
            ProjectAccess::query()
                ->where('tester_id', $request->user()->id)
                ->with('project')
                ->get()
                ->filter(fn(ProjectAccess $a) => $a->project?->acceptsAnswers())
        )->sortByDesc('id')->values();

        $progress = $this->progress->forAccesses($accesses);

        return $this->respond(
            $accesses
                ->map(fn(ProjectAccess $access) => (new HomeProjectResource($access, $progress[$access->id]))->resolve())
                ->all()
        );
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

        return (new ProjectResource($access, $progress[$access->id]))->resolve();
    }

    // Replace with Dev 1's response helper if the project has one.
    private function respond(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }
}
