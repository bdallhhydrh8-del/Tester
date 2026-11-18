<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Expected upload failures (wrong type, too large, closed session...).
 * The message is looked up in lang/ar/uploads.php using the error code.
 */
class UploadException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
        array $replace = [],
    ) {
        parent::__construct(__('uploads.'.$errorCode, $replace));
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

    /** These are user mistakes, not bugs: returning true keeps them out of the log. */
    public function report(): bool
    {
        return true;
    }
}