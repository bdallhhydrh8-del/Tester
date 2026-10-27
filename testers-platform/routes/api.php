<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\TesterAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthController::class, 'adminLogin']);

// Public link and tester login endpoints: 30 requests per minute per IP
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/tester/login', [TesterAuthController::class, 'login']);
    Route::get('/links/{token}', [LinkController::class, 'show']);
    Route::post('/links/{token}/register', [LinkController::class, 'register']);
    Route::post('/links/{token}/login', [LinkController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Temporary route to test the role middleware. Delete it later.
    Route::middleware('role:admin')->get('/admin/ping', fn () => response()->json(['message' => 'pong']));
});