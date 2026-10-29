<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Services\Results\CsvWriter;
use App\Services\Results\ExportRowBuilder;
use App\Services\Results\ResultsService;
use App\Support\ApiResponse;

class ExportController extends Controller
{
    // POST /projects/{project}/export/csv   body: { selected_ids?: int[] }
    public function csv(ExportRequest $r, int $project, ResultsService $results, ExportRowBuilder $rows, CsvWriter $csv)
    {
        $answers = $results->exportAnswers($project, $r->ids());

        return response()->streamDownload(
            fn () => $csv->write(fopen('php://output', 'w'), $rows->rows($answers)),
            "project-{$project}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    // GET /projects/{project}/report   data for the print-friendly page
    public function report(int $project, ResultsService $results)
    {
        return ApiResponse::ok($results->dashboard($project) + ['tasks' => $results->taskStats($project)]);
    }
}
