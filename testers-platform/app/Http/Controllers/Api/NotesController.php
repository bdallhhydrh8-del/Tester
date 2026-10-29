<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Results\ResultsService;
use App\Support\ApiResponse;

class NotesController extends Controller
{
    // GET /projects/{project}/notes
    public function __invoke(ListRequest $r, int $project, ResultsService $results)
    {
        return ApiResponse::paged($results->notes($project), $r->pageNumber(), $r->perPage());
    }
}
