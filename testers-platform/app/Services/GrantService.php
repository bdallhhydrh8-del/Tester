<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GrantService
{
    /**
     * Records that the tester joined the link and gives them access to every
     * project in it. Safe to call twice: existing rows are never changed.
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
     * Creates the missing project_accesses rows. Dev 3 owns the table: only
     * tester_id, project_id and invitation_id are written here.
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
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            // The unique (tester_id, project_id) index makes this safe against races
            DB::table('project_accesses')->insertOrIgnore($rows);
        }
    }

    /**
     * FR-I7: projects added to a link later are granted to every tester who
     * already joined it. Existing rows are never changed, so invitation_id
     * keeps pointing at the first link that granted the access.
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
}