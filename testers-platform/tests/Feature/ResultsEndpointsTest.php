<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Uses only Dev 1's users table and role middleware, plus the fixture data source.
class ResultsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'role' => Role::Admin, 'status' => 'active', 'name' => 'Admin',
            'username' => 'admin-'.uniqid(), 'password' => 'secret-pass',
        ]);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin(), 'sanctum');
    }

    public function test_logged_out_user_gets_401(): void
    {
        $this->getJson('/api/projects/1/results/tasks')->assertUnauthorized();
    }

    public function test_a_tester_gets_403(): void
    {
        $tester = User::create([
            'role' => Role::Tester, 'status' => 'active', 'name' => 'T',
            'phone' => '+10000000001', 'tester_number' => 1,
        ]);

        $this->actingAs($tester, 'sanctum')->getJson('/api/projects/1/results/tasks')->assertForbidden();
    }

    public function test_tasks_returns_one_row_per_card(): void
    {
        $this->asAdmin()->getJson('/api/projects/1/results/tasks')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure(['data' => [['position', 'reached', 'answers', 'avg_seconds', 'median_seconds', 'success_rate', 'avg_difficulty']], 'message']);
    }

    public function test_testers_list_is_paged_filterable_and_private(): void
    {
        $this->asAdmin()->getJson('/api/projects/1/results/testers?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 8)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonMissingPath('data.0.name')
            ->assertJsonMissingPath('data.0.phone');

        $this->asAdmin()->getJson('/api/projects/1/results/testers?tester_status=finished')
            ->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_bad_filter_value_is_422(): void
    {
        $this->asAdmin()->getJson('/api/projects/1/results/testers?tester_status=banana')->assertUnprocessable();
    }

    public function test_unknown_project_and_unknown_tester_are_404(): void
    {
        $this->asAdmin()->getJson('/api/projects/99/dashboard')->assertNotFound();
        $this->asAdmin()->getJson('/api/projects/1/results/testers/999')->assertNotFound();
    }

    public function test_dashboard_report_notes_summary_and_overview_respond(): void
    {
        $this->asAdmin()->getJson('/api/projects/1/dashboard')->assertOk()->assertJsonPath('data.finished', 3);
        $this->asAdmin()->getJson('/api/projects/1/report')->assertOk()->assertJsonCount(4, 'data.tasks');
        $this->asAdmin()->getJson('/api/projects/1/notes')->assertOk()->assertJsonStructure(['data' => [['id', 'tester_number', 'notes']], 'meta']);
        $this->asAdmin()->getJson('/api/projects/1/summary')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->asAdmin()->getJson('/api/overview')->assertOk()->assertJsonPath('data.live_projects', 2);
    }

    public function test_csv_export_starts_with_bom_and_header_row(): void
    {
        $response = $this->asAdmin()->post('/api/projects/1/export/csv');
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('tester_number,card_position,seconds,success,difficulty,notes', $csv);
    }
}
