<?php

namespace App\Services;

use App\Models\Card;
use App\Models\ProjectAccess;
use App\Models\TaskResponse;

/**
 * Decides where a tester is in a project's cards, and what the card screen needs.
 */
class CardFlowService
{
    public const STEP_READY = 'ready';        // instructions, timer not running
    public const STEP_RUNNING = 'running';    // Start pressed, timer running
    public const STEP_FEEDBACK = 'feedback';  // answered Yes: ease, custom questions, notes, file
    public const STEP_PROBLEM = 'problem';    // answered No: reason, description, images
    public const STEP_FINISHED = 'finished';  // every task submitted: thank-you
    public const STEP_ENDED = 'ended';        // the tester ended the test early: thank-you  // Done pressed, timer stopped

    /**
     * The card the tester must do next: the first card, in fixed order, without a submitted answer.
     * Null when every card is submitted. Deleted cards are skipped automatically (soft delete).
     */
    public function currentCard(ProjectAccess $access): ?Card
    {
        if ($access->status === ProjectAccess::ENDED_EARLY) {
            return null; // an ended attempt has no next card; the middleware then refuses every task route
        }
        $submittedCardIds = TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->whereNotNull('submitted_at')
            ->whereNotNull('card_id') // a NULL inside NOT IN (...) would make the query return nothing
            ->pluck('card_id');

        return Card::query()
            ->where('project_id', $access->project_id)
            ->whereNotIn('id', $submittedCardIds)
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }

    /** Custom questions as an array, whether or not the Card model casts the column. */
    public function customQuestions(Card $card): array
    {
        $value = $card->custom_questions;

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    /** A copy of the card as the tester sees it. Saved on the response when the task starts. */
    public function snapshot(Card $card): array
    {
        return [
            'position' => $card->position,
            'title' => $card->title,
            'instructions' => $card->instructions,
            'ask_ease' => (bool) $card->ask_ease,
            'ask_notes' => (bool) $card->ask_notes,
            'ask_file' => (bool) $card->ask_file,
            'required' => (bool) $card->required,
            'custom_questions' => $this->customQuestions($card),
        ];
    }

    public function step(?TaskResponse $response): string
    {
        if (! $response || ! $response->task_started_at) {
            return self::STEP_READY;
        }

        if (! $response->task_ended_at) {
            return self::STEP_RUNNING;
        }

        return $response->success === true ? self::STEP_FEEDBACK : self::STEP_PROBLEM;
    }

    /**
     * Everything the card screen needs. Returned by current-card (also used to resume),
     * starttask, donetask and submit, so the client always gets the same shape.
     * 'card' is an array from definition(): the saved snapshot once the task started,
     * otherwise the live card.     */
    public function state(ProjectAccess $access): array
    {
        $ids = Card::query()
            ->where('project_id', $access->project_id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        $total = $ids->count();

        // The same keys in every state, so the client never checks for missing ones.
        $base = [
            'card' => null,
            'total' => $total,
            'task_started_at' => null,
            'task_ended_at' => null,
            'success' => null,
            'server_time' => now()->toIso8601ZuluString(),
        ];

        // Ended early: no card, even if cards are left.
        if ($access->status === ProjectAccess::ENDED_EARLY) {
            return $base + ['step' => self::STEP_ENDED, 'position' => $this->completedCount($access)];
        }

        $card = $this->currentCard($access);

        if (! $card) {
            return $base + ['step' => self::STEP_FINISHED, 'position' => $total];
        }

        $response = TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->where('card_id', $card->id)
            ->first();

        return [
            'step' => $this->step($response),
            'card' => $this->cardPayload($card, $this->definition($card, $response)),
            'position' => ($ids->search($card->id) ?: 0) + 1,
            'total' => $total,
            'task_started_at' => $response?->task_started_at?->toIso8601ZuluString(),
            'task_ended_at' => $response?->task_ended_at?->toIso8601ZuluString(),
            'success' => $response?->success,
            'server_time' => $base['server_time'],
        ];
    }

    /** What the tester's screen shows. Built from the saved copy, so it never changes under them. */
    private function cardPayload(Card $card, array $def): array
    {
        return [
            'id' => $card->id, // the only live value: ids never change, and the URLs need it
            'title' => $def['title'] ?? null,
            'instructions' => $def['instructions'] ?? null,
            'ask_ease' => (bool) ($def['ask_ease'] ?? false),
            'ask_notes' => (bool) ($def['ask_notes'] ?? false),
            'ask_file' => (bool) ($def['ask_file'] ?? false),
            'required' => (bool) ($def['required'] ?? false),
            'custom_questions' => $def['custom_questions'] ?? [],
        ];
    }

    private function completedCount(ProjectAccess $access): int
    {
        return TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->whereNotNull('submitted_at')
            ->count();
    }

    /** The card as the tester sees it: the saved copy once the task has started, else the live card. */
    public function definition(Card $card, ?TaskResponse $response): array
    {
        return $response?->card_snapshot ?: $this->snapshot($card);
    }
}
