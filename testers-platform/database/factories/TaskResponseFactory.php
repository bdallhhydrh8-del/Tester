<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\ProjectAccess;
use App\Models\TaskResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Responses in every state, for the shared seed script (Dev 4 builds the results screens on it).
 *
 *   TaskResponse::factory()->submitted()->create([...])      normal answer
 *   TaskResponse::factory()->idle()->create([...])           duration above the 30-minute cap
 *   TaskResponse::factory()->inTask()->create([...])         started, not finished yet
 *
 * @extends Factory<TaskResponse>
 */
class TaskResponseFactory extends Factory
{
    protected $model = TaskResponse::class;

    public function definition(): array
    {
        return [
            'project_access_id' => ProjectAccess::factory(),
            'card_id' => Card::factory(), // needs a CardFactory from Dev 2
            // Mirrors Dev 2's cards columns (there is no title column).
            'card_snapshot' => [
                'position' => 1,
                'instructions' => "افتح شاشة التسجيل في التطبيق واضغط على زر «إنشاء حساب جديد».\nأدخل بيانات تجريبية ثم أكمل التسجيل.",
                'ask_success' => true,
                'ask_difficulty' => true,
                'ask_notes' => true,
                'ask_file' => false,
                'required' => true,
                'custom_questions' => [],
            ],
        ];
    }

    /** The tester pressed "Start task" and is still doing it. */
    public function inTask(): static
    {
        return $this->state(fn () => [
            'task_started_at' => now()->subMinutes(random_int(1, 20)),
        ]);
    }

    /** "Done with task" pressed, questions not submitted yet. */
    public function taskDone(?int $seconds = null): static
    {
        return $this->state(fn () => $this->timing($seconds) + [
            'success' => fake()->boolean(80),
        ]);
    }

    /** Fully answered. */
    public function submitted(?int $seconds = null): static
    {
        return $this->state(function () use ($seconds) {
            $timing = $this->timing($seconds);

            return $timing + [
                'success' => fake()->boolean(80),
                'difficulty' => random_int(1, 5),
                'notes' => fake()->randomElement([
                    'الخطوات واضحة',
                    'لم أجد زر التسجيل بسهولة',
                    'التطبيق بطيء عند فتح الشاشة',
                    'ظهرت لي رسالة خطأ بعد إدخال البيانات',
                    'كل شيء يعمل بشكل جيد',
                ]),
                'submitted_at' => $timing['task_ended_at']->copy()->addSeconds(random_int(20, 180)),
            ];
        });
    }

    /** A duration above the default 30-minute cap, for the "possibly idle" flag. */
    public function idle(): static
    {
        return $this->submitted(random_int(2400, 7200));
    }

    /** Start and end times that agree with the duration, as the server would save them. */
    private function timing(?int $seconds): array
    {
        $seconds ??= random_int(30, 600);
        $started = now()->subHours(random_int(1, 72))->subSeconds($seconds);

        return [
            'task_started_at' => $started,
            'task_ended_at' => $started->copy()->addSeconds($seconds),
            'duration_seconds' => $seconds,
        ];
    }
}