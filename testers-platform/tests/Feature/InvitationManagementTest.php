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

    private array $frontend = ['Origin' => 'http://localhost:3000'];

    protected function setUp(): void
    {
        parent::setUp();

        // Stand-ins, only while Dev 2's and Dev 3's real migrations are not merged yet.
        // Delete these two blocks once the real tables exist.
        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->string('link')->nullable();
                $table->text('note')->nullable();
                $table->string('status')->default('live');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // FR-I10: one attempt per tester, project and link
        if (! Schema::hasTable('project_accesses')) {
            Schema::create('project_accesses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tester_id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('invitation_id');
                $table->string('status')->default('not_started');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('last_opened_at')->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->string('device_type')->nullable();
                $table->timestamps();
                $table->unique(['tester_id', 'project_id', 'invitation_id']);
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

    private function accessCount(int $testerId, int $projectId): int
    {
        return DB::table('project_accesses')
            ->where('tester_id', $testerId)
            ->where('project_id', $projectId)
            ->count();
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
            ->postJson('/api/invitations', ['name' => 'Week 1', 'project_ids' => [$p1, $p2]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.projects_count', 2)
            ->assertJsonPath('data.joined_count', 0)
            ->assertJsonMissingPath('data.expires_at');

        $token = $response->json('data.token');
        $this->assertSame(40, strlen($token));

        $expectedUrl = rtrim(config('tester.frontend_url'), '/') . '/join/' . $token;
        $response->assertJsonPath('data.share_url', $expectedUrl);
        $this->assertStringStartsWith('https://wa.me/?text=', $response->json('data.whatsapp_url'));
        $this->assertStringContainsString(rawurlencode($expectedUrl), $response->json('data.whatsapp_url'));

        $this->assertDatabaseCount('invitation_project', 2);
        $this->assertDatabaseHas('invitations', ['token' => $token, 'status' => 'active', 'created_by' => $this->admin()->id]);
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

        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_list_shows_status_and_counts_and_filters_by_status(): void
    {
        $project = $this->project();
        $active = $this->link([$project], ['name' => 'Active link']);
        $this->link([$project], ['name' => 'Disabled link', 'status' => 'disabled']);

        DB::table('invitation_testers')->insert([
            'invitation_id' => $active->id, 'tester_id' => $this->tester(1)->id, 'joined_at' => now(),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations')->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations?status=active')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active link')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.joined_count', 1)
            ->assertJsonPath('data.0.projects_count', 1);

        $this->actingAs($admin, 'sanctum')->getJson('/api/invitations?status=disabled')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'disabled');
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
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.projects.0.name', 'Shop app')
            ->assertJsonPath('data.testers.0.tester_number', 7)
            ->assertJsonMissingPath('data.testers.0.phone')
            ->assertJsonMissingPath('data.testers.0.name');

        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/invitations/999999')->assertNotFound();
    }

    public function test_links_cannot_be_edited(): void
    {
        $link = $this->link([$this->project()], ['name' => 'Original']);

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/invitations/{$link->id}", ['name' => 'Renamed'])
            ->assertStatus(405);

        $this->assertSame('Original', $link->fresh()->name);
    }

    public function test_disable_is_permanent_and_blocks_registration(): void
    {
        $link = $this->link([$this->project()]);
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->postJson("/api/invitations/{$link->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('message', 'Invitation link disabled.');

        // Disabling twice is safe
        $this->actingAs($admin, 'sanctum')->postJson("/api/invitations/{$link->id}/disable")
            ->assertOk()->assertJsonPath('data.status', 'disabled');

        // There is no way back
        $this->actingAs($admin, 'sanctum')->postJson("/api/invitations/{$link->id}/activate")->assertNotFound();

        $this->withHeaders($this->frontend)
            ->postJson("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '777123456'])
            ->assertStatus(409)->assertJsonPath('code', 'link_disabled');

        $this->assertDatabaseHas('invitations', ['id' => $link->id, 'status' => 'disabled']);
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

    public function test_a_project_added_to_a_second_link_gives_the_tester_one_row_per_link(): void
    {
        $p1 = $this->project('First');
        $p2 = $this->project('Second');
        $link1 = $this->link([$p1]);
        $link2 = $this->link([$p2]);

        $tester = $this->tester(1);
        $grants = app(GrantService::class);
        $grants->grant($tester, $link2); // attempt on p2 through link 2, opened now
        $grants->grant($tester, $link1); // joined link 1 as well

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/invitations/{$link1->id}/projects", ['project_ids' => [$p2]])->assertOk();

        // Two attempts for p2: one per link
        $this->assertSame(2, $this->accessCount($tester->id, $p2));

        $viaLink2 = DB::table('project_accesses')
            ->where('tester_id', $tester->id)->where('project_id', $p2)->where('invitation_id', $link2->id)->first();
        $viaLink1 = DB::table('project_accesses')
            ->where('tester_id', $tester->id)->where('project_id', $p2)->where('invitation_id', $link1->id)->first();

        $this->assertNotNull($viaLink2->last_opened_at);
        $this->assertNull($viaLink1->last_opened_at);

        // The tester has not opened link 1 for p2, so the attempt through link 2 stays current
        $this->assertSame($link2->id, (int) $grants->currentAccess($tester->id, $p2)->invitation_id);
    }

    public function test_a_different_link_starts_a_new_attempt_and_the_last_opened_is_current(): void
    {
        $project = $this->project();
        $link1 = $this->link([$project]);
        $link2 = $this->link([$project]);
        $tester = $this->tester(1);
        $grants = app(GrantService::class);

        $grants->grant($tester, $link1);
        $this->travel(5)->minutes();
        $grants->grant($tester, $link2);

        $this->assertSame(2, $this->accessCount($tester->id, $project));
        $this->assertSame($link2->id, (int) $grants->currentAccess($tester->id, $project)->invitation_id);

        // Opening the first link again makes its attempt current again
        $this->travel(5)->minutes();
        $grants->grant($tester, $link1);

        $this->assertSame(2, $this->accessCount($tester->id, $project));
        $this->assertSame($link1->id, (int) $grants->currentAccess($tester->id, $project)->invitation_id);
    }

    public function test_opening_the_same_link_twice_keeps_one_attempt_and_its_progress(): void
    {
        $project = $this->project();
        $link = $this->link([$project]);
        $tester = $this->tester(1);
        $grants = app(GrantService::class);

        $grants->grant($tester, $link);

        // The tester makes progress on the attempt (Dev 3's code does this)
        DB::table('project_accesses')->where('tester_id', $tester->id)
            ->update(['status' => 'in_progress', 'started_at' => now()]);

        $openedBefore = DB::table('project_accesses')->where('tester_id', $tester->id)->value('last_opened_at');

        $this->travel(10)->minutes();
        $grants->grant($tester, $link);

        $this->assertSame(1, $this->accessCount($tester->id, $project));

        $row = $grants->currentAccess($tester->id, $project);
        $this->assertSame('in_progress', $row->status);
        $this->assertNotNull($row->started_at);
        $this->assertNotSame($openedBefore, $row->last_opened_at);
    }

    public function test_projects_cannot_be_added_to_a_disabled_link(): void
    {
        $p1 = $this->project('First');
        $p2 = $this->project('Second');
        $link = $this->link([$p1], ['status' => 'disabled']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/invitations/{$link->id}/projects", ['project_ids' => [$p2]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'link_disabled')
            ->assertJsonPath('message', 'This link is disabled and cannot get new projects.');

        $this->assertDatabaseCount('invitation_project', 1);
    }
}