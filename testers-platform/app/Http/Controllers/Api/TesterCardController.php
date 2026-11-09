<?php

namespace App\Http\Controllers\Api;

use App\Events\TesterFinishedProject;
use App\Exceptions\TesterFlowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tester\DoneTaskRequest;
use App\Http\Requests\Tester\SubmitCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\ProjectAccess;
use App\Models\TaskResponse;
use App\Services\CardFlowService;
use App\Services\FileAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


/**
 * The card flow. Every route here sits behind the tester.project middleware, which has
 * already checked the project is live, the tester has access, and (for the card routes)
 * that the card is the expected one. The results are read from the request attributes.
 *
 * Per card:  starttask -> donetask -> submit.   Timing uses server time only.
 */
class TesterCardController extends Controller
{
    public function __construct(
        private CardFlowService $flow,
        private FileAttachmentService $files,
    ) {}

    /** GET /tester/projects/{project}/current-card : the next card. Also used to resume. */
    public function currentCard(Request $request): JsonResponse
    {
        return $this->respond($this->payload($request->attributes->get('project_access')));
    }

    /** POST .../cards/{card}/starttask : the timer starts. A second press keeps the first time. */
    public function startTask(Request $request): JsonResponse
    {
        /** @var ProjectAccess $access */
        $access = $request->attributes->get('project_access');
        /** @var Card $card */
        $card = $request->attributes->get('card');

        // createOrFirst survives two presses at the same moment (unique access + card).
        // The snapshot is taken now: it is the card the tester saw before starting.
        $response = TaskResponse::createOrFirst(
            ['project_access_id' => $access->id, 'card_id' => $card->id],
            ['card_snapshot' => $this->flow->snapshot($card)],
        );

        if ($response->isSubmitted()) {
            throw new TesterFlowException('already_submitted');
        }

        // Only the first press counts, so refreshing and pressing again never restarts the clock.
        TaskResponse::whereKey($response->id)
            ->whereNull('task_started_at')
            ->update(['task_started_at' => now()]);

        // Safety net in case the project start call was skipped.
        ProjectAccess::whereKey($access->id)
            ->where('status', ProjectAccess::NOT_STARTED)
            ->update(['status' => ProjectAccess::IN_PROGRESS, 'started_at' => now()]);

        return $this->respond($this->payload($access));
    }

    /** POST .../cards/{card}/donetask : the timer stops, and the tester answers "success". */
    public function doneTask(DoneTaskRequest $request): JsonResponse
    {
        $access = $request->attributes->get('project_access');
        $card = $request->attributes->get('card');

        $response = $this->responseFor($access, $card);

        if (! $response || ! $response->task_started_at) {
            throw new TesterFlowException('task_not_started');
        }

        if ($response->isSubmitted()) {
            throw new TesterFlowException('already_submitted');
        }

        $ended = now();
        $seconds = max(0, (int) round($response->task_started_at->diffInSeconds($ended, true)));

        // Only the first press saves the end time and the duration. A later press
        // (the tester changes their answer) can only change "success".
        TaskResponse::whereKey($response->id)
            ->whereNull('task_ended_at')
            ->update(['task_ended_at' => $ended, 'duration_seconds' => $seconds]);

        if ($this->flow->definition($card, $response)['ask_success']) {
            TaskResponse::whereKey($response->id)->update(['success' => $request->boolean('success')]);
        }

        return $this->respond($this->payload($access));
    }

    /** POST .../cards/{card}/submit : saves the answers and moves to the next card. */
    public function submit(SubmitCardRequest $request): JsonResponse
    {
        /** @var ProjectAccess $access */
        $access = $request->attributes->get('project_access');
        /** @var Card $card */
        $card = $request->attributes->get('card');

        $finished = DB::transaction(function () use ($request, $access, $card) {
            // Locked, so a double tap cannot submit twice.
            $response = TaskResponse::query()
                ->where('project_access_id', $access->id)
                ->where('card_id', $card->id)
                ->lockForUpdate()
                ->first();

            if (! $response || ! $response->task_started_at) {
                throw new TesterFlowException('task_not_started');
            }

            if ($response->isSubmitted()) {
                throw new TesterFlowException('already_submitted');
            }

            if (! $response->task_ended_at) {
                throw new TesterFlowException('task_not_finished');
            }

            if ($this->flow->definition($card, $response)['ask_success'] && $response->success === null) {
                throw new TesterFlowException('success_missing');
            }

            $response->update([
                'difficulty' => $request->validated('difficulty'),
                'notes' => $request->validated('notes'),
                'custom_answers' => $request->validated('custom_answers'),
                'submitted_at' => now(),
            ]);

            // All or nothing: a bad file id rolls the whole submit back.
            $this->files->attach($response, $request->user(), $request->validated('file_ids', []));

            $isLast = $this->flow->currentCard($access) === null;

            if ($isLast) {
                $access->update(['status' => ProjectAccess::FINISHED, 'finished_at' => now()]);
            }

            return $isLast;
        });

        // After the commit, so listeners always see the saved answers.
        if ($finished) {
            event(new TesterFinishedProject($access->fresh()));
        }

        return $this->respond($this->payload($access));
    }

    private function responseFor(ProjectAccess $access, Card $card): ?TaskResponse
    {
        return TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->where('card_id', $card->id)
            ->first();
    }

    /** The card screen state, with the card turned into its tester-facing resource. */
    private function payload(ProjectAccess $access): array
    {
        return $this->flow->state($access);
    }

    // Replace with Dev 1's response helper if the project has one.
    private function respond(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }
}
