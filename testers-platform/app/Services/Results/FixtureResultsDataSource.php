<?php

namespace App\Services\Results;

/** Fake data so every endpoint works before Dev 2 and Dev 3 finish their tables. Project 1 has data, project 2 is empty. */
class FixtureResultsDataSource implements ResultsDataSource
{
    private const PROJECTS = [
        1 => ['id' => 1, 'name' => 'Shop app', 'type' => 'website', 'status' => 'live'],
        2 => ['id' => 2, 'name' => 'Booking site', 'type' => 'android', 'status' => 'live'],
    ];

    private const CARDS = [
        1 => 'Create an account',
        2 => 'Find a product',
        3 => 'Add it to the cart',
        4 => 'Complete checkout',
    ];

    // number => [status, device, invitation, cards answered, started_at, finished_at]
    private const TESTERS = [
        1 => ['finished', 'phone', 1, 4, '2026-10-20T09:00:00Z', '2026-10-20T09:20:00Z'],
        2 => ['finished', 'desktop', 1, 4, '2026-10-21T10:00:00Z', '2026-10-21T10:25:00Z'],
        3 => ['finished', 'phone', 1, 4, '2026-10-22T11:00:00Z', '2026-10-22T12:30:00Z'],
        4 => ['in_progress', 'phone', 1, 2, '2026-10-23T09:00:00Z', null],
        5 => ['in_progress', 'desktop', 1, 3, '2026-10-24T09:00:00Z', null],
        6 => ['in_progress', 'phone', 2, 1, '2026-10-25T09:00:00Z', null],
        7 => ['not_started', 'phone', 2, 0, null, null],
        8 => ['in_progress', 'desktop', 2, 2, '2026-10-26T09:00:00Z', null],
    ];

    public function projectHeader(int $project): ?array
    {
        return self::PROJECTS[$project] ?? null;
    }

    public function liveProjects(): array
    {
        $live = array_filter(self::PROJECTS, fn ($p) => $p['status'] === 'live');

        return array_values(array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name']], $live));
    }

    public function cards(int $project): array
    {
        if ($project !== 1) {
            return [];
        }

        $cards = [];
        foreach (self::CARDS as $position => $instructions) {
            $cards[] = ['card_id' => $position, 'position' => $position, 'instructions' => $instructions, 'removed' => false];
        }

        return $cards;
    }

    public function accesses(int $project, array $filters = []): array
    {
        if ($project !== 1) {
            return [];
        }

        $rows = [];
        foreach (self::TESTERS as $number => [$status, $device, $invitation, , $started, $finished]) {
            $rows[] = [
                'tester_number' => $number,
                'status' => $status,
                'device_type' => $device,
                'browser' => 'Chrome',
                'os' => $device === 'phone' ? 'Android' : 'Windows',
                'invitation_id' => $invitation,
                'started_at' => $started,
                'finished_at' => $finished,
            ];
        }

        return array_values(array_filter($rows, fn ($r) => (! isset($filters['device_type']) || $r['device_type'] === $filters['device_type'])
            && (! isset($filters['tester_status']) || $r['status'] === $filters['tester_status'])
            && (! isset($filters['invitation_id']) || $r['invitation_id'] === (int) $filters['invitation_id'])));
    }

    public function answers(int $project, array $filters = []): array
    {
        $numbers = array_column($this->accesses($project, $filters), 'tester_number');

        return array_values(array_filter($this->allAnswers(), fn ($a) => in_array($a['tester_number'], $numbers, true)));
    }

    public function testerAnswers(int $project, int $number): ?array
    {
        $access = collect($this->accesses($project))->firstWhere('tester_number', $number);
        if (! $access) {
            return null;
        }

        return [
            'access' => $access,
            'answers' => array_values(array_filter($this->allAnswers(), fn ($a) => $a['tester_number'] === $number)),
        ];
    }

    public function recentFinishes(int $limit): array
    {
        $rows = collect($this->accesses(1))
            ->whereNotNull('finished_at')
            ->sortByDesc('finished_at')
            ->take($limit)
            ->map(fn ($a) => [
                'tester_number' => $a['tester_number'],
                'project_id' => 1,
                'project' => self::PROJECTS[1]['name'],
                'finished_at' => $a['finished_at'],
            ]);

        return $rows->values()->all();
    }

    public function idleCapSeconds(): int
    {
        return 30 * 60;
    }

    /** Tester 3's answer on card 2 is 4000 seconds, so it shows up as idle. Testers in progress have one unsubmitted row. */
    private function allAnswers(): array
    {
        $rows = [];
        $id = 0;

        foreach (self::TESTERS as $number => [$status, , , $reached]) {
            for ($p = 1; $p <= 4; $p++) {
                $submitted = $p <= $reached;
                $started = $submitted || ($status === 'in_progress' && $p === $reached + 1);
                if (! $started) {
                    continue;
                }

                $sum = $number + $p;
                $rows[] = [
                    'id' => ++$id,
                    'tester_number' => $number,
                    'card_id' => $p,
                    'card_position' => $p,
                    'instructions' => self::CARDS[$p],
                    'submitted' => $submitted,
                    'duration_seconds' => $submitted ? (($number === 3 && $p === 2) ? 4000 : 30 + $number * 10 + $p * 15) : null,
                    'success' => $submitted ? ($sum % 3 !== 0) : null,
                    'difficulty' => $submitted ? 1 + $sum % 5 : null,
                    'notes' => ($submitted && $sum % 2 === 0)
                        ? ($number === 2 ? 'ممتاز' : "Note from tester $number on task $p")
                        : null,
                ];
            }
        }

        return $rows;
    }
}
