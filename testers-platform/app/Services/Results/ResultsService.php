<?php

namespace App\Services\Results;

use Illuminate\Support\Arr;

class ResultsService
{
    public function __construct(
        private ResultsDataSource $data,
        private StatsCalculator $stats,
        private DashboardBuilder $dashboards,
    ) {}

    /** Throws 404 for an unknown project. Every public method starts here. */
    public function header(int $project): array
    {
        return $this->data->projectHeader($project) ?? abort(404);
    }

    /** One row per card: reached, answers, idle_flagged, average and median time, success rate, difficulty. */
    public function taskStats(int $project, array $filters = []): array
    {
        $this->header($project);
        $cap = $this->data->idleCapSeconds();
        $byCard = collect($this->data->answers($project, $filters))->groupBy('card_id');

        return collect($this->data->cards($project))->map(function ($card) use ($byCard, $cap) {
            $rows = $byCard->get($card['card_id'], collect());
            $submitted = $rows->where('submitted', true);

            return $card
                + ['reached' => $rows->pluck('tester_number')->unique()->count()]
                + $this->stats->taskStats($submitted->map(fn ($a) => [
                    'duration_seconds' => $a['duration_seconds'],
                    'success' => $a['success'],
                    'difficulty' => $a['difficulty'],
                ])->values()->all(), $cap);
        })->values()->all();
    }

    /** Whitelisted fields only, so a name or phone can never leak into this list. */
    public function testers(int $project, array $filters = []): array
    {
        $this->header($project);
        $answered = collect($this->data->answers($project))->where('submitted', true)->countBy('tester_number');

        return collect($this->data->accesses($project, $filters))
            ->map(fn ($a) => Arr::only($a, ['tester_number', 'status', 'device_type', 'browser', 'os', 'started_at', 'finished_at'])
                + ['answered_count' => $answered->get($a['tester_number'], 0)])
            ->values()->all();
    }

    public function testerDetail(int $project, int $number): array
    {
        $this->header($project);
        $found = $this->data->testerAnswers($project, $number) ?? abort(404);
        $cap = $this->data->idleCapSeconds();
        $a = $found['access'];

        return [
            'tester_number' => $a['tester_number'],
            'status' => $a['status'],
            'device_type' => $a['device_type'],
            'browser' => $a['browser'],
            'os' => $a['os'],
            'started_at' => $a['started_at'],
            'finished_at' => $a['finished_at'],
            'answers' => collect($found['answers'])->where('submitted', true)->sortBy('card_position')->values()
                ->map(fn ($r) => [
                    'position' => $r['card_position'],
                    'instructions' => $r['instructions'],
                    'duration_seconds' => $r['duration_seconds'],
                    'idle' => $r['duration_seconds'] !== null && $r['duration_seconds'] > $cap,
                    'success' => $r['success'],
                    'difficulty' => $r['difficulty'],
                    'notes' => $r['notes'],
                    'files' => [],   // filled in once Dev 3's files exist
                ])->all(),
        ];
    }

    public function dashboard(int $project): array
    {
        $header = $this->header($project);

        return ['project' => $header]
            + $this->dashboards->build($this->data->accesses($project), $this->taskStats($project));
    }

    /** Submitted notes only. The id is what the front end sends back as selected_ids. */
    public function notes(int $project): array
    {
        $this->header($project);

        return collect($this->data->answers($project))
            ->where('submitted', true)
            ->filter(fn ($a) => trim((string) $a['notes']) !== '')
            ->map(fn ($a) => Arr::only($a, ['id', 'tester_number', 'card_position', 'notes']))
            ->values()->all();
    }

    /** Rows shaped for ExportRowBuilder. An empty $ids list means the whole project. */
    public function exportAnswers(int $project, array $ids = []): array
    {
        $this->header($project);

        return collect($this->data->answers($project))
            ->where('submitted', true)
            ->when($ids, fn ($c) => $c->whereIn('id', $ids))
            ->map(fn ($a) => Arr::only($a, ['tester_number', 'card_position', 'duration_seconds', 'success', 'difficulty', 'notes']))
            ->values()->all();
    }

    public function overview(): array
    {
        $live = $this->data->liveProjects();
        $accesses = collect($live)->flatMap(fn ($p) => $this->data->accesses($p['id']))->all();

        $slowest = collect($live)
            ->flatMap(fn ($p) => collect($this->taskStats($p['id']))
                ->whereNotNull('avg_seconds')
                ->map(fn ($t) => $t + ['project_id' => $p['id'], 'project' => $p['name']]))
            ->sortByDesc('avg_seconds')->take(5)->values()->all();

        return [
            'live_projects' => count($live),
            'active_testers' => collect($accesses)->where('status', 'in_progress')->count(),
            'completion_rate' => $this->dashboards->build($accesses)['completion_rate'],
            'slowest_tasks' => $slowest,
            'recent_activity' => $this->data->recentFinishes(10),
        ];
    }
}
