<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\NotesController;
use App\Http\Controllers\Api\OverviewController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ResultsController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TesterAuthController;
use App\Http\Controllers\Api\TesterCardController;
use App\Http\Controllers\Api\TesterController;
use App\Http\Controllers\Api\TesterProjectController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;


// Public (no login). Rate limited per IP.

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/tester/login', [TesterAuthController::class, 'login']);

    Route::get('/links/{token}', [LinkController::class, 'show']);
    Route::post('/links/{token}/register', [LinkController::class, 'register']);
    Route::post('/links/{token}/login', [LinkController::class, 'login']);
});

// Any logged-in user

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});


//Tester (Dev 3). Final URLs: /api/tester/...

Route::middleware(['auth:sanctum', 'role:tester'])
    ->prefix('tester')
    ->group(function () {

        Route::get('home', [TesterProjectController::class, 'home']);

        // Uploads (screenshots on the feedback and problem pages)
        Route::middleware('throttle:uploads')
            ->prefix('uploads')
            ->group(function () {
                Route::post('/', [UploadController::class, 'store']);
                Route::post('init', [UploadController::class, 'init']);
                Route::post('{id}/chunk', [UploadController::class, 'chunk'])->whereNumber('id');
                Route::get('{id}', [UploadController::class, 'status'])->whereNumber('id');
                Route::post('{id}/complete', [UploadController::class, 'complete'])->whereNumber('id');
                Route::delete('{id}', [UploadController::class, 'destroy'])->whereNumber('id');
            });

        // Everything under a project: live + tester has access
        Route::prefix('projects/{project}')
            ->middleware('tester.project')
            ->group(function () {
                Route::get('/', [TesterProjectController::class, 'show']);
                Route::post('start', [TesterProjectController::class, 'start']);
                Route::get('current-card', [TesterCardController::class, 'currentCard']);

                // Also: the card must be the one the tester must do next
                Route::middleware('tester.project:expect-card')->group(function () {
                    Route::post('cards/{card}/starttask', [TesterCardController::class, 'startTask']);
                    Route::post('cards/{card}/donetask', [TesterCardController::class, 'doneTask']); // v4. Dev 3 replaces it with answer/problem
                    Route::post('cards/{card}/submit', [TesterCardController::class, 'submit']);
                });
            });
    });


//Any admin (admin or SuperAdmin)

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // Own profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    // Testers (Dev 1)
    Route::get('/testers', [TesterController::class, 'index']);
    Route::get('/testers/{id}', [TesterController::class, 'show'])->whereNumber('id');
    Route::patch('/testers/{id}', [TesterController::class, 'update'])->whereNumber('id');

    // Invitation links (Dev 1). 
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::get('/invitations/{id}', [InvitationController::class, 'show'])->whereNumber('id');
    Route::post('/invitations/{id}/disable', [InvitationController::class, 'disable'])->whereNumber('id');
    Route::post('/invitations/{id}/projects', [InvitationController::class, 'addProjects'])->whereNumber('id');

    // Projects, cards, default cards (Dev 2)
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project');
    Route::post('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->whereNumber('project');

    Route::get('/projects/{project}/cards', [CardController::class, 'index'])->whereNumber('project');
    Route::post('/projects/{project}/cards', [CardController::class, 'store'])->whereNumber('project');
    Route::put('/projects/{project}/cards/order', [CardController::class, 'reorder'])->whereNumber('project');
    Route::patch('/cards/{card}', [CardController::class, 'update'])->whereNumber('card');
    Route::delete('/cards/{card}', [CardController::class, 'destroy'])->whereNumber('card');

    Route::get('/settings/default-cards', [SettingController::class, 'showDefaultCards']);
    Route::put('/settings/default-cards', [SettingController::class, 'updateDefaultCards']);

    // Results, overview, export (Dev 4)
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

    // Private files (tester screenshots)
    Route::get('/files/{id}', [FileController::class, 'show'])->whereNumber('id');
});


//SuperAdmin only (Dev 1)

Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::get('/admins/{id}', [AdminController::class, 'show'])->whereNumber('id');
    Route::patch('/admins/{id}', [AdminController::class, 'update'])->whereNumber('id');
    Route::post('/admins/{id}/deactivate', [AdminController::class, 'deactivate'])->whereNumber('id');
    Route::post('/admins/{id}/activate', [AdminController::class, 'activate'])->whereNumber('id');
    Route::delete('/admins/{id}', [AdminController::class, 'destroy'])->whereNumber('id');
});