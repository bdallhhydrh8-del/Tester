<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\TesterAuthController;
use App\Http\Controllers\Api\UploadController;
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
    Route::middleware('role:admin')->get('/admin/ping', fn() => response()->json(['message' => 'pong']));
});

// ---- Dev 3: tester uploads ----
Route::middleware(['auth:sanctum', 'role:tester'])
    ->prefix('tester')
    ->group(function () {
        Route::middleware('throttle:uploads')
            ->prefix('uploads')
            ->group(function () {
                Route::post('init', [UploadController::class, 'init']);
                Route::post('{id}/chunk', [UploadController::class, 'chunk'])->whereNumber('id');
                Route::get('{id}', [UploadController::class, 'status'])->whereNumber('id');
                Route::post('{id}/complete', [UploadController::class, 'complete'])->whereNumber('id');
                Route::delete('{id}', [UploadController::class, 'destroy'])->whereNumber('id');
            });
    });
