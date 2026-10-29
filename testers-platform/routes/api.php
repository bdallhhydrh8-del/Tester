<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\TesterAuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\SettingController;
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

    // Project management endpoints: only for admin and super_admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::patch('/projects/{project}', [ProjectController::class, 'update']);

        Route::get('/projects/{project}/cards', [CardController::class, 'index']);
        Route::post('/projects/{project}/cards', [CardController::class, 'store']);
        Route::put('/projects/{project}/cards/order', [CardController::class, 'reorder']);
        Route::patch('/cards/{card}', [CardController::class, 'update']);
        Route::delete('/cards/{card}', [CardController::class, 'destroy']);

        Route::get('/settings/default-cards', [SettingController::class, 'showDefaultCards']);
        Route::put('/settings/default-cards', [SettingController::class, 'updateDefaultCards']);
    });

    // Temporary route to test the role middleware. Delete it later.
    Route::middleware('role:admin')->get('/admin/ping', fn () => response()->json(['message' => 'pong']));
});