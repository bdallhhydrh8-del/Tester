<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Results\ResultsService;
use App\Support\ApiResponse;

class ResultsController extends Controller
{
    public function __construct(private ResultsService $results) {}

    // GET /projects/{project}/results/tasks
    public function tasks(ListRequest $r, int $project)
    {
        return ApiResponse::ok($this->results->taskStats($project, $r->filters()));
    }

    // GET /projects/{project}/results/testers
    public function testers(ListRequest $r, int $project)
    {
        return ApiResponse::paged($this->results->testers($project, $r->filters()), $r->pageNumber(), $r->perPage());
    }

    // GET /projects/{project}/results/testers/{number}
    public function tester(int $project, int $number)
    {
        return ApiResponse::ok($this->results->testerDetail($project, $number));
    }
}