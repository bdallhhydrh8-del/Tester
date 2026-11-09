<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GrantService
{
    /**
     * Records that the tester joined the link and gives them access to every
     * project in it. Safe to call twice: opening the same link again never
     * creates a second attempt.
     */
    public function grant(User $tester, Invitation $invitation): void
    {
        DB::transaction(function () use ($tester, $invitation) {
            DB::table('invitation_testers')->insertOrIgnore([
                'invitation_id' => $invitation->id,
                'tester_id' => $tester->id,
                'joined_at' => now(),
            ]);

            $this->grantProjects($tester, $invitation->id, $invitation->projectIds());
        });
    }

    /**
     * FR-I10: one attempt per tester, project and link. Opening a link creates
     * the attempt, or on a repeat only moves last_opened_at (and updated_at),
     * so status, times and answers stay. Dev 3 owns the table.
     */
    public function grantProjects(User $tester, int $invitationId, iterable $projectIds): void
    {
        $now = now();
        $rows = [];

        foreach ($projectIds as $projectId) {
            $rows[] = [
                'tester_id' => $tester->id,
                'project_id' => $projectId,
                'invitation_id' => $invitationId,
                'last_opened_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('project_accesses')->upsert(
                $rows,
                ['tester_id', 'project_id', 'invitation_id'],
                ['last_opened_at', 'updated_at']
            );
        }
    }

    /**
     * FR-I7: projects added to a link later are granted to every tester who
     * already joined it. A tester who already has the project through another
     * link gets a new attempt, and it becomes current only when they open this
     * link (last_opened_at stays empty until then). Existing rows never change.
     */
    public function grantProjectsToJoinedTesters(Invitation $invitation, iterable $projectIds): void
    {
        $projectIds = collect($projectIds)->values();

        if ($projectIds->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('invitation_testers')
            ->where('invitation_id', $invitation->id)
            ->orderBy('tester_id')
            ->chunk(500, function ($joined) use ($invitation, $projectIds, $now) {
                $rows = [];

                foreach ($joined as $row) {
                    foreach ($projectIds as $projectId) {
                        $rows[] = [
                            'tester_id' => $row->tester_id,
                            'project_id' => $projectId,
                            'invitation_id' => $invitation->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('project_accesses')->insertOrIgnore($rows);
            });
    }

    /**
     * The attempt the tester is on now for a project: the one whose link they
     * opened last. Attempts never opened (last_opened_at empty) come last.
     */
    public function currentAccess(int $testerId, int $projectId): ?object
    {
        return DB::table('project_accesses')
            ->where('tester_id', $testerId)
            ->where('project_id', $projectId)
            ->orderByRaw('last_opened_at is null')
            ->orderByDesc('last_opened_at')
            ->orderByDesc('id')
            ->first();
    }
}