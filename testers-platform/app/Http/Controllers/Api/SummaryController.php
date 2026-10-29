<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSummary;
use App\Support\ApiResponse;

class SummaryController extends Controller
{
    public function __invoke(int $project)
    {
        $row = AiSummary::where('project_id', $project)->first();

        return ApiResponse::ok([
            'status'       => $row->status ?? 'pending',
            'summary'      => $row->summary ?? null,
            'notes_count'  => $row->notes_count ?? 0,
            'generated_at' => $row->generated_at ?? null,
        ]);
    }
}

