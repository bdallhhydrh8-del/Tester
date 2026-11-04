<?php

namespace App\Http\Middleware;

use App\Exceptions\TesterFlowException;
use App\Models\Card;
use App\Models\Project;
use App\Models\ProjectAccess;
use App\Models\TaskResponse;
use App\Services\CardFlowService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared by every tester route that has a {project} in its path (Dev 3 and Dev 4).
 *
 *   tester.project              the project is live and the tester has access to it
 *   tester.project:expect-card  also: the {card} is the one the tester must do next
 *
 * Use the plain form for routes that work on any card, such as problem reports.
 * After it passes, controllers can read:
 *   $request->attributes->get('project')          the Project
 *   $request->attributes->get('project_access')   this tester's ProjectAccess
 *   $request->attributes->get('card')             the Card (only with :expect-card)
 */
class EnsureTesterProjectAccess
{
    public function __construct(private CardFlowService $flow)
    {
    }

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $project = $this->resolveProject($request);

        // One answer for "does not exist", "not live" and "no access":
        // a tester cannot tell which projects exist.
        if (! $project || ! $project->acceptsAnswers()) {
            throw new TesterFlowException('project_not_found', 404);
        }

        $access = ProjectAccess::query()
            ->where('tester_id', $request->user()->id)
            ->where('project_id', $project->id)
            ->first();

        if (! $access) {
            throw new TesterFlowException('project_not_found', 404);
        }

        if ($mode === 'expect-card') {
            $request->attributes->set('card', $this->checkCard($request, $project, $access));
        }

        $request->attributes->set('project', $project);
        $request->attributes->set('project_access', $access);

        return $next($request);
    }

    private function checkCard(Request $request, Project $project, ProjectAccess $access): Card
    {
        $param = $request->route('card');
        $card = $param instanceof Card ? $param : Card::find($param); // deleted cards are not found

        if (! $card || (int) $card->project_id !== (int) $project->id) {
            throw new TesterFlowException('card_not_expected', 404);
        }

        $expected = $this->flow->currentCard($access);

        if ($expected && $expected->id === $card->id) {
            return $card;
        }

        // A card that is already submitted is let through, so the controller can answer
        // 409 already_submitted (a double tap on Submit) instead of a confusing 404.
        $alreadySubmitted = TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->where('card_id', $card->id)
            ->whereNotNull('submitted_at')
            ->exists();

        if (! $alreadySubmitted) {
            throw new TesterFlowException('card_not_expected', 404);
        }

        return $card;
    }

    /** Works whether or not Laravel already turned {project} into a model. */
    private function resolveProject(Request $request): ?Project
    {
        $param = $request->route('project');

        return $param instanceof Project ? $param : Project::find($param);
    }
}