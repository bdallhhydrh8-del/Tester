<?php

namespace App\Http\Controllers\Api;

use App\Events\TesterFinishedProject;
use App\Exceptions\TesterFlowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tester\SubmitCardRequest;
use App\Contracts\FailedTaskIssueCreator;
use App\Http\Requests\Tester\AnswerRequest;
use App\Http\Requests\Tester\ProblemRequest;
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
 * Per task:  starttask -> answer (Yes or No)
 *              Yes -> submit  (feedback page)
 *              No  -> problem (problem page: continue to the next task, or end the test)
 * Timing uses server time only: the timer runs from starttask until answer.
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

    /** POST .../cards/{card}/answer : the timer stops and the answer is final. */
    public function answer(AnswerRequest $request): JsonResponse
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

        // whereNull makes a double tap safe: only one request can save the answer.
        $saved = TaskResponse::whereKey($response->id)
            ->whereNull('task_ended_at')
            ->update([
                'task_ended_at' => $ended,
                'duration_seconds' => $seconds,
                'success' => $request->boolean('success'),
            ]);

        if ($saved === 0) {
            throw new TesterFlowException('already_answered');
        }

        return $this->respond($this->payload($access));
    }

    public function submit(SubmitCardRequest $request): JsonResponse
    {
        $access = $request->attributes->get('project_access');
        $card = $request->attributes->get('card');

        $outcome = DB::transaction(function () use ($request, $access, $card) {
            $response = $this->assertAnswered($this->lockedResponse($access, $card));

            if ($response->success !== true) {
                throw new TesterFlowException('wrong_step');
            }

            $response->update([
                'ease' => $request->validated('ease'),
                'notes' => $request->validated('notes'),
                'custom_answers' => $request->validated('custom_answers'),
                'submitted_at' => now(), // the task is done only now
            ]);

            // All or nothing: a bad file id rolls the whole submit back.
            $this->files->attach($response, $request->user(), $request->validated('file_ids', []));

            return $this->finishIfLast($access);
        });

        $this->announce($access, $outcome); // after the commit

        return $this->respond($this->payload($access->fresh()));
    }

    /** POST .../cards/{card}/problem : the tester says what went wrong, then continues or ends the test. */
    public function problem(ProblemRequest $request, FailedTaskIssueCreator $issues): JsonResponse
    {
        $access = $request->attributes->get('project_access');
        $card = $request->attributes->get('card');

        $outcome = DB::transaction(function () use ($request, $issues, $access, $card) {
            $response = $this->assertAnswered($this->lockedResponse($access, $card));

            if ($response->success !== false) {
                throw new TesterFlowException('wrong_step');
            }

            // Dev 4 creates the issue and attaches the screenshots, in this same transaction.
            $issues->createFromFailedTask(
                $access,
                $card,
                $request->validated('reason'),
                $request->validated('description'),
                $request->validated('file_ids', []),
            );

            $response->update(['submitted_at' => now()]); // the task is done only now

            if ($request->validated('action') === 'end') {
                $access->update(['status' => ProjectAccess::ENDED_EARLY]); // finished_at stays empty

                return TesterFinishedProject::ENDED_EARLY;
            }

            return $this->finishIfLast($access); // the next card, or finished if this was the last
        });

        $this->announce($access, $outcome);

        return $this->respond($this->payload($access->fresh()));
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

    private function lockedResponse(ProjectAccess $access, Card $card): ?TaskResponse
    {
        return TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->where('card_id', $card->id)
            ->lockForUpdate()
            ->first();
    }

    /** The task must have started, been answered, and not be done yet. */
    private function assertAnswered(?TaskResponse $response): TaskResponse
    {
        if (! $response || ! $response->task_started_at) throw new TesterFlowException('task_not_started');
        if ($response->isSubmitted()) throw new TesterFlowException('already_submitted');
        if (! $response->task_ended_at) throw new TesterFlowException('task_not_answered');

        return $response;
    }

    /** No card left means the attempt is finished. Returns the outcome, or null. */
    private function finishIfLast(ProjectAccess $access): ?string
    {
        if ($this->flow->currentCard($access) !== null) {
            return null;
        }

        $access->update(['status' => ProjectAccess::FINISHED, 'finished_at' => now()]);

        return TesterFinishedProject::FINISHED;
    }

    private function announce(ProjectAccess $access, ?string $outcome): void
    {
        if ($outcome) {
            event(new TesterFinishedProject($access->fresh(), $outcome));
        }
    }
}
