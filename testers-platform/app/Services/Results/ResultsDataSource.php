<?php

namespace App\Services\Results;

/**
 * Everything the results pages read from the database, as plain arrays.
 * Today FixtureResultsDataSource answers it. Later an EloquentResultsDataSource
 * answers it from Dev 2's and Dev 3's tables, and nothing else changes.
 *
 * Privacy rule: no implementation may return a tester's name or phone, only tester_number.
 */
interface ResultsDataSource
{
    /** @return array{id:int, name:string, type:string, status:string}|null  null = unknown project */
    public function projectHeader(int $project): ?array;

    /** @return array<int, array{id:int, name:string}> */
    public function liveProjects(): array;

    /** @return array<int, array{card_id:int, position:int, instructions:string, removed:bool}> ordered by position */
    public function cards(int $project): array;

    /**
     * @param  array{device_type?:string, tester_status?:string, invitation_id?:int}  $filters
     * @return array<int, array{tester_number:int, status:string, device_type:?string, browser:?string, os:?string, invitation_id:?int, started_at:?string, finished_at:?string}>
     */
    public function accesses(int $project, array $filters = []): array;

    /**
     * Every response row of the matching testers, submitted or still in progress.
     *
     * @return array<int, array{id:int, tester_number:int, card_id:int, card_position:int, instructions:string, submitted:bool, duration_seconds:?int, success:?bool, difficulty:?int, notes:?string}>
     */
    public function answers(int $project, array $filters = []): array;

    /** @return array{access: array, answers: array}|null  null = this tester has no access to the project */
    public function testerAnswers(int $project, int $number): ?array;

    /** @return array<int, array{tester_number:int, project_id:int, project:string, finished_at:string}> newest first */
    public function recentFinishes(int $limit): array;

    public function idleCapSeconds(): int;
}
