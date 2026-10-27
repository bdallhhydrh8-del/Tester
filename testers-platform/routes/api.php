<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthController::class, 'adminLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Temporary route to test the role middleware. Delete it later.
    Route::middleware('role:admin')->get('/admin/ping', fn () => response()->json(['message' => 'pong']));
});