<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Expected failures in the tester flow (project hidden, wrong card, task not started...).
 * The message is looked up in lang/ar/tester.php using the error code.
 *
 * Codes: project_not_found, card_not_expected (404);
 *        task_not_started, task_not_finished, already_submitted, success_missing (409).
 */
class TesterFlowException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 409,
        array $replace = [],
    ) {
        parent::__construct(__('tester.'.$errorCode, $replace));
    }

    /** Same shape as the other API errors: message, errors, code. */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => (object) [],
            'code' => $this->errorCode,
        ], $this->status);
    }

    /** User-level outcomes, not bugs: keep them out of the log. */
    public function report(): bool
    {
        return true;
    }
}