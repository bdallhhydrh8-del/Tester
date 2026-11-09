<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function ok(mixed $data, string $message = ''): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message]);
    }

    public static function paged(array $rows, int $page, int $perPage): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => count($rows)],
            'message' => '',
        ]);
    }
}