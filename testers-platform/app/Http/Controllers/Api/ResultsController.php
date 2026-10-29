<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function tasks(int $project)
{
    return ApiResponse::ok(json_decode(file_get_contents(database_path('fixtures/results/tasks.json')), true));
}
}
