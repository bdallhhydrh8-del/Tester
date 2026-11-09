<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;      // DEV 4
use App\Http\Controllers\Api\ExportController;         // DEV 4
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\NotesController;          // DEV 4
use App\Http\Controllers\Api\OverviewController;       // DEV 4
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResultsController;        // DEV 4
use App\Http\Controllers\Api\SummaryController;        // DEV 4
use App\Http\Controllers\Api\TesterAuthController;
use App\Http\Controllers\Api\TesterController;
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

    // Any admin (admin or SuperAdmin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::get('/testers', [TesterController::class, 'index']);
        Route::get('/testers/{id}', [TesterController::class, 'show'])->whereNumber('id');
        Route::patch('/testers/{id}', [TesterController::class, 'update'])->whereNumber('id');

        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::get('/invitations/{id}', [InvitationController::class, 'show'])->whereNumber('id');
        Route::post('/invitations/{id}/disable', [InvitationController::class, 'disable'])->whereNumber('id');
        Route::post('/invitations/{id}/projects', [InvitationController::class, 'addProjects'])->whereNumber('id');


        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::patch('/projects/{project}', [ProjectController::class, 'update']);
        Route::post('/projects/{project}/status', [ProjectController::class, 'updateStatus']);
        
        Route::get('/projects/{project}/cards', [CardController::class, 'index']);
        Route::post('/projects/{project}/cards', [CardController::class, 'store']);
        Route::put('/projects/{project}/cards/order', [CardController::class, 'reorder']);
        Route::patch('/cards/{card}', [CardController::class, 'update']);
        Route::delete('/cards/{card}', [CardController::class, 'destroy']);

        Route::get('/settings/default-cards', [SettingController::class, 'showDefaultCards']);
        Route::put('/settings/default-cards', [SettingController::class, 'updateDefaultCards']);
    });

    // SuperAdmin only
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admins', [AdminController::class, 'index']);
        Route::post('/admins', [AdminController::class, 'store']);
        Route::get('/admins/{id}', [AdminController::class, 'show'])->whereNumber('id');
        Route::patch('/admins/{id}', [AdminController::class, 'update'])->whereNumber('id');
        Route::post('/admins/{id}/deactivate', [AdminController::class, 'deactivate'])->whereNumber('id');
        Route::post('/admins/{id}/activate', [AdminController::class, 'activate'])->whereNumber('id');
        Route::delete('/admins/{id}', [AdminController::class, 'destroy'])->whereNumber('id');
    });
});

// ---- DEV 4: results, overview, export (any admin) ----
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/overview', OverviewController::class);

    Route::prefix('projects/{project}')->where(['project' => '[0-9]+'])->group(function () {
        Route::get('dashboard', DashboardController::class);
        Route::get('results/tasks', [ResultsController::class, 'tasks']);
        Route::get('results/testers', [ResultsController::class, 'testers']);
        Route::get('results/testers/{number}', [ResultsController::class, 'tester'])->whereNumber('number');
        Route::get('notes', NotesController::class);
        Route::get('summary', SummaryController::class);
        Route::post('export/csv', [ExportController::class, 'csv']);
        Route::get('report', [ExportController::class, 'report']);
    });
});
// ---- end DEV 4 ----