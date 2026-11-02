<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Services\GrantService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stand-ins, only while Dev 2's and Dev 3's real migrations are not merged yet.
        // They copy the columns in the team document. Delete these two blocks later.
        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->string('link')->nullable();
                $table->text('note')->nullable();
                $table->string('status')->default('disabled');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_accesses')) {
            Schema::create('project_accesses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tester_id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('invitation_id')->nullable();
                $table->string('status')->default('not_started');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->string('device_type')->nullable();
                $table->timestamps();
                $table->unique(['tester_id', 'project_id']);
            });
        }
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'adm'],
            ['role' => 'admin', 'status' => 'active', 'name' => 'Admin', 'password' => 'adminpass123']
        );
    }

    private function tester(int $number): User
    {
        return User::create(['role' => 'tester', 'status' => 'active', 'name' => "Tester {$number}",
            'phone' => '+96777100000' . $number, 'tester_number' => $number]);
    }

    private function project(string $name = 'Shop app'): int
    {
        return DB::table('projects')->insertGetId([
            'name' => $name, 'type' => 'website', 'link' => 'https://example.test', 'note' => 'demo',
            'status' => 'live', 'created_by' => $this->admin()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A link made directly in the database, with the given projects. */
    private function link(array $projectIds, array $overrides = []): Invitation
    {
        $link = Invitation::create(array_merge([
            'name' => 'Link ' . Str::random(4),
            'token' => Str::random(40),
            'created_by' => $this->admin()->id,
        ], $overrides));

        foreach ($projectIds as $projectId) {
            DB::table('invitation_project')->insert(['invitation_id' => $link->id, 'project_id' => $projectId]);
        }

        return $link;
    }

    public function test_only_admins_can_manage_invitations(): void
    {
        // Not logged in: must be checked before any actingAs()
        $this->getJson('/api/invitations')->assertStatus(401);

        $this->actingAs($this->tester(1), 'sanctum')->getJson('/api/invitations')->assertStatus(403);
        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/invitations')->assertOk();
    }

    public function test_create_a_link_with_projects_and_share_urls(): void
    {
        $p1 = $this->project('App one');
        $p2 = $this->project('App two');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/invitations', [
                'name' => 'Week 1',
                'project_ids' => [$p1, $p2],
                'expires_at' => now()->addDays(7)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.projects_count', 2)
            ->assertJsonPath('data.joined_count', 0);

        $token = $response->json('data.token');
        $this->assertSame(40, strlen($token));

        $expectedUrl = rtrim(config('tester.frontend_url'), '/') . '/join/' . $token;
        $response->assertJsonPath('data.share_url', $expectedUrl);
        $this->assertStringStartsWith('https://wa.me/?text=', $response->json('data.whatsapp_url'));
        $this->assertStringContainsString(rawurlencode($expectedUrl), $response->json('data.whatsapp_url'));

        $this->assertDatabaseCount('invitation_project', 2);
        $this->assertDatabaseHas('invitations', ['token' => $token, 'created_by' => $this->admin()->id]);
    }

    public function test_create_validates_input(): void
    {
        $admin = $this->admin();
        $project = $this->project();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/invitations', ['project_ids' => [$project]])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/invitations', ['name' => 'X', 'project_ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('project_ids');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/invitations', ['name' => 'X', 'project_ids' => [999999]])
            ->assertStatus(422)->assertJsonValidationErrors('project_ids.0');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/invitations', ['name' => 'X', 'project_ids' => [$project], 'expires_at' => now()->subDay()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors('expires_at');

        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_list_shows_state_and_counts_and_filters_by_state(): void
    {
        $project = $this->project();
        $active = $this->link([$project], ['name' => 'Active link']);
        $this->link([$project], ['name' => 'Stopped link', 'stopped_at' => now()]);
        $this->link([$project], ['name' => 'Expired link', 'expires_at' => now()->subDay()]);

        DB::table('invitation_testers')->insert([
            'invitation_id' => $active->id, 'tester_id' => $this->tester(1)->id, 'joined_at' => now(),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations?state=active')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active link')
            ->assertJsonPath('data.0.joined_count', 1)
            ->assertJsonPath('data.0.projects_count', 1);

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations?state=stopped')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.state', 'stopped');

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations?state=expired')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.state', 'expired');
    }

    public function test_show_lists_projects_and_testers_without_private_data(): void
    {
        $project = $this->project('Shop app');
        $link = $this->link([$project]);
        $tester = $this->tester(7);

        DB::table('invitation_testers')->insert([
            'invitation_id' => $link->id, 'tester_id' => $tester->id, 'joined_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'sanctum')->getJson("/api/invitations/{$link->id}")
            ->assertOk()
            ->assertJsonPath('data.projects.0.name', 'Shop app')
            ->assertJsonPath('data.testers.0.tester_number', 7)
            ->assertJsonMissingPath('data.testers.0.phone')
            ->assertJsonMissingPath('data.testers.0.name');

        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/invitations/999999')->assertNotFound();
    }

    public function test_update_name_and_expiry(): void
    {
        $link = $this->link([$this->project()], ['expires_at' => now()->addDays(3)]);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/invitations/{$link->id}", ['name' => 'Renamed', 'expires_at' => now()->addDays(10)->toIso8601String()])
            ->assertOk()->assertJsonPath('data.name', 'Renamed');

        // Sending null removes the expiry date
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/invitations/{$link->id}", ['expires_at' => null])
            ->assertOk()->assertJsonPath('data.expires_at', null);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/invitations/{$link->id}", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_stop_blocks_registration_and_can_be_repeated(): void
    {
        $link = $this->link([$this->project()]);

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/invitations/{$link->id}/stop")
            ->assertOk()->assertJsonPath('data.state', 'stopped');

        $stoppedAt = $link->fresh()->stopped_at;

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/invitations/{$link->id}/stop")->assertOk();
        $this->assertEquals($stoppedAt, $link->fresh()->stopped_at);

        $this->withHeaders(['Origin' => 'http://localhost:3000'])
            ->postJson("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '777123456'])
            ->assertStatus(409)->assertJsonPath('code', 'link_stopped');
    }

    public function test_added_projects_reach_testers_who_already_joined(): void
    {
        $p1 = $this->project('First');
        $p2 = $this->project('Second');
        $link = $this->link([$p1]);

        $joined = $this->tester(1);
        $stranger = $this->tester(2);
        app(GrantService::class)->grant($joined, $link);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/invitations/{$link->id}/projects", ['project_ids' => [$p2]])
            ->assertOk()->assertJsonPath('data.projects_count', 2);

        $this->assertDatabaseHas('project_accesses', [
            'tester_id' => $joined->id, 'project_id' => $p2, 'invitation_id' => $link->id,
        ]);
        $this->assertDatabaseMissing('project_accesses', ['tester_id' => $stranger->id, 'project_id' => $p2]);

        // Calling it again changes nothing
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/invitations/{$link->id}/projects", ['project_ids' => [$p2]])->assertOk();

        $this->assertSame(1, DB::table('project_accesses')->where('project_id', $p2)->count());
        $this->assertDatabaseCount('invitation_project', 2);
    }

    public function test_added_projects_never_overwrite_the_first_link_that_granted_access(): void
    {
        $p1 = $this->project('First');
        $p2 = $this->project('Second');
        $link1 = $this->link([$p1]);
        $link2 = $this->link([$p2]);

        $tester = $this->tester(1);
        app(GrantService::class)->grant($tester, $link2); // access to p2 through link 2
        app(GrantService::class)->grant($tester, $link1); // joined link 1 as well

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/invitations/{$link1->id}/projects", ['project_ids' => [$p2]])->assertOk();

        $this->assertDatabaseHas('project_accesses', [
            'tester_id' => $tester->id, 'project_id' => $p2, 'invitation_id' => $link2->id,
        ]);
        $this->assertSame(1, DB::table('project_accesses')->where('project_id', $p2)->count());
    }
}