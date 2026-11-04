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
    public const STEP_READY = 'ready';          // instructions shown, timer not running
    public const STEP_IN_TASK = 'in_task';      // Start pressed, timer running
    public const STEP_QUESTIONS = 'questions';  // Done pressed, timer stopped

    /**
     * The card the tester must do next: the first card, in fixed order, without a submitted answer.
     * Null when every card is submitted. Deleted cards are skipped automatically (soft delete).
     */
    public function currentCard(ProjectAccess $access): ?Card
    {
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
            'title' => $card->getAttribute('title'), // Dev 2 is adding this column
            'instructions' => $card->instructions,
            'ask_success' => (bool) $card->ask_success,
            'ask_difficulty' => (bool) $card->ask_difficulty,
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

        return $response->task_ended_at ? self::STEP_QUESTIONS : self::STEP_IN_TASK;
    }

    /**
     * Everything the card screen needs. Returned by current-card (also used to resume),
     * starttask, donetask and submit, so the client always gets the same shape.
     * 'card' is the Card model; the controller turns it into a resource.
     */
    public function state(ProjectAccess $access): array
    {
        $ids = Card::query()
            ->where('project_id', $access->project_id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        $total = $ids->count();
        $card = $this->currentCard($access);
        $serverTime = now()->toIso8601ZuluString(); // lets the client show a timer that survives a refresh

        if (! $card) {
            return [
                'finished' => true,
                'card' => null,
                'position' => $total,
                'total' => $total,
                'step' => null,
                'task_started_at' => null,
                'task_ended_at' => null,
                'success' => null,
                'server_time' => $serverTime,
            ];
        }

        $response = TaskResponse::query()
            ->where('project_access_id', $access->id)
            ->where('card_id', $card->id)
            ->first();

        return [
            'finished' => false,
            'card' => $card,
            'position' => ($ids->search($card->id) ?: 0) + 1,
            'total' => $total,
            'step' => $this->step($response),
            'task_started_at' => $response?->task_started_at?->toIso8601ZuluString(),
            'task_ended_at' => $response?->task_ended_at?->toIso8601ZuluString(),
            'success' => $response?->success,
            'server_time' => $serverTime,
        ];
    }
}