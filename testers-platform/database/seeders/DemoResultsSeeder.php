<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoResultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Shop app', 'Booking site'] as $name) {
            $project = Project::create(['name' => $name, 'type' => 'website', 'link' => 'https://example.com',
                'note' => 'Demo', 'status' => 'live', 'created_by' => 1]);

            $cards = collect(range(1, 4))->map(fn ($i) => Card::create([
                'project_id' => $project->id, 'position' => $i, 'instructions' => "Task $i",
                'ask_success' => true, 'ask_difficulty' => true, 'ask_notes' => true,
                'ask_file' => false, 'required' => true]));

            foreach (range(1, 15) as $n) {
                $tester = User::firstOrCreate(['phone' => sprintf('+1000000%04d', $n)],
                    ['role' => 'tester', 'name' => "Tester $n", 'tester_number' => $n]);
                $reached = rand(1, 4);

                $access = ProjectAccess::create([
                    'tester_id' => $tester->id, 'project_id' => $project->id,
                    'status' => $reached === 4 ? 'finished' : 'in_progress',
                    'started_at' => now()->subDays(rand(1, 5)),
                    'finished_at' => $reached === 4 ? now()->subHours(rand(1, 20)) : null,
                    'device_type' => collect(['phone', 'desktop'])->random(),
                    'browser' => 'Chrome', 'os' => 'Android']);

                foreach ($cards->take($reached) as $card) {
                    TaskResponse::create([
                        'project_access_id' => $access->id, 'card_id' => $card->id,
                        'card_snapshot' => $card->only(['instructions', 'position', 'required']),
                        'duration_seconds' => rand(1, 10) === 1 ? 4000 : rand(30, 300), // some idle
                        'success' => (bool) rand(0, 1), 'difficulty' => rand(1, 5),
                        'notes' => rand(0, 2) ? "Note from tester $n on task {$card->position}" : null,
                        'submitted_at' => now()]);
                }
            }
        }
    }
}
