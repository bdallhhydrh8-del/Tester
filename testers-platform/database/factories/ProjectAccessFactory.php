<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAccess>
 */
class ProjectAccessFactory extends Factory
{
    protected $model = ProjectAccess::class;

    public function definition(): array
    {
        return [
            'tester_id' => User::factory()->state(['role' => 'tester']),
            'project_id' => Project::factory(), // needs a ProjectFactory from Dev 2
            'invitation_id' => null,
            'status' => ProjectAccess::NOT_STARTED,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => ProjectAccess::IN_PROGRESS,
            'started_at' => now()->subMinutes(random_int(5, 600)),
        ] + $this->device());
    }

    public function finished(): static
    {
        return $this->state(function () {
            $started = now()->subMinutes(random_int(120, 4000));

            return [
                'status' => ProjectAccess::FINISHED,
                'started_at' => $started,
                'finished_at' => $started->copy()->addMinutes(random_int(10, 90)),
            ] + $this->device();
        });
    }

    /** A believable mix of browsers and devices, for the device split on the dashboard. */
    private function device(): array
    {
        [$browser, $os, $type] = fake()->randomElement([
            ['Chrome', 'Android', 'phone'],
            ['Chrome', 'Android', 'phone'],
            ['Safari', 'iOS', 'phone'],
            ['Chrome', 'Windows', 'desktop'],
            ['Safari', 'macOS', 'desktop'],
        ]);

        return ['browser' => $browser, 'os' => $os, 'device_type' => $type];
    }
}