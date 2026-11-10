<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Results\ResultsService;
use App\Support\ApiResponse;

class OverviewController extends Controller
{
    // GET /overview
    public function __invoke(ResultsService $results)
    {
        return ApiResponse::ok($results->overview());
    }
}
