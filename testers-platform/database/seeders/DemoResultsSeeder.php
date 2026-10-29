<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\{Card, Project, ProjectAccess, TaskResponse, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoResultsSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::table('tester_counter')->exists()) {
            DB::table('tester_counter')->insert(['last_number' => 0]);
        }

        $admin = User::firstOrCreate(
            ['username' => 'demo-admin'],
            ['role' => Role::Admin, 'status' => 'active', 'name' => 'Demo Admin', 'password' => 'change-me-now']
        );

        $invitationId = DB::table('invitations')->insertGetId([
            'name' => 'Demo link', 'token' => Str::random(40),
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['Shop app', 'Booking site'] as $name) {
            $project = Project::create([
                'name' => $name, 'type' => 'website', 'link' => 'https://example.com',
                'note' => 'Demo', 'status' => 'live', 'created_by' => $admin->id,
            ]);
            DB::table('invitation_project')->insertOrIgnore([
                'invitation_id' => $invitationId, 'project_id' => $project->id,
            ]);

            $cards = collect(range(1, 4))->map(fn ($i) => Card::create([
                'project_id' => $project->id, 'position' => $i, 'instructions' => "Task $i",
                'ask_success' => true, 'ask_difficulty' => true, 'ask_notes' => true,
                'ask_file' => false, 'required' => true,
            ]));

            foreach (range(1, 15) as $n) {
                $tester = $this->tester($n);
                DB::table('invitation_testers')->insertOrIgnore([
                    'invitation_id' => $invitationId, 'tester_id' => $tester->id, 'joined_at' => now(),
                ]);

                $reached = rand(1, 4);
                $access = ProjectAccess::create([
                    'tester_id' => $tester->id, 'project_id' => $project->id,
                    'invitation_id' => $invitationId,
                    'status' => $reached === 4 ? 'finished' : 'in_progress',
                    'started_at' => now()->subDays(rand(1, 5)),
                    'finished_at' => $reached === 4 ? now()->subHours(rand(1, 20)) : null,
                    'device_type' => collect(['phone', 'desktop'])->random(),
                    'browser' => 'Chrome', 'os' => 'Android',
                ]);

                foreach ($cards->take($reached) as $card) {
                    TaskResponse::create([
                        'project_access_id' => $access->id, 'card_id' => $card->id,
                        'card_snapshot' => $card->only(['instructions', 'position', 'required']),
                        'duration_seconds' => rand(1, 10) === 1 ? 4000 : rand(30, 300), // some idle
                        'success' => (bool) rand(0, 1), 'difficulty' => rand(1, 5),
                        'notes' => rand(0, 2) ? "Note from tester $n on task {$card->position}" : null,
                        'submitted_at' => now(),
                    ]);
                }
            }
        }
    }

    private function tester(int $n): User
    {
        $phone = sprintf('+1000000%04d', $n);

        return User::where('phone', $phone)->first() ?? User::create([
            'role' => Role::Tester, 'status' => 'active', 'name' => "Tester $n",
            'phone' => $phone, 'tester_number' => $this->nextTesterNumber(),
        ]);
    }

    private function nextTesterNumber(): int
    {
        return DB::transaction(function () {
            $next = DB::table('tester_counter')->lockForUpdate()->value('last_number') + 1;
            DB::table('tester_counter')->update(['last_number' => $next]);
            return $next;
        });
    }
}
