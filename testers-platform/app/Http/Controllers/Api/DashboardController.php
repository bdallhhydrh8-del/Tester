<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Results\ResultsService;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    // GET /projects/{project}/dashboard
    public function __invoke(int $project, ResultsService $results)
    {
        return ApiResponse::ok($results->dashboard($project));
    }
}
