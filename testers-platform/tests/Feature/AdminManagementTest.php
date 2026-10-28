<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'role' => 'super_admin', 'status' => 'active', 'name' => 'Boss',
            'username' => 'boss', 'password' => 'bosspass123',
        ]);
    }

    private function admin(string $username = 'a1', string $status = 'active'): User
    {
        return User::create([
            'role' => 'admin', 'status' => $status, 'name' => 'Admin ' . $username,
            'username' => $username, 'password' => 'adminpass123',
        ]);
    }

       public function test_only_the_super_admin_can_manage_admins(): void
    {
        // Not logged in: must be checked before any actingAs()
        $this->getJson('/api/admins')->assertStatus(401);

        $tester = User::create(['role' => 'tester', 'status' => 'active', 'name' => 'T',
            'phone' => '+967777000001', 'tester_number' => 1]);

        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admins')->assertStatus(403);
        $this->actingAs($this->admin('a2'), 'sanctum')
            ->postJson('/api/admins', ['name' => 'X', 'username' => 'x', 'password' => 'password1'])
            ->assertStatus(403);
        $this->actingAs($tester, 'sanctum')->getJson('/api/admins')->assertStatus(403);
    }

    public function test_super_admin_creates_and_lists_admins(): void
    {
        $boss = $this->superAdmin();

        $this->actingAs($boss, 'sanctum')
            ->postJson('/api/admins', ['name' => 'Sami', 'username' => 'sami', 'password' => 'password1'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('password1', User::where('username', 'sami')->first()->password));

        $this->admin('second', 'deactivated');

        // The SuperAdmin is not in the list: 2 admins only
        $this->actingAs($boss, 'sanctum')->getJson('/api/admins')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($boss, 'sanctum')->getJson('/api/admins?status=deactivated')
            ->assertJsonCount(1, 'data');
    }

    public function test_create_validates_input_and_unique_username(): void
    {
        $boss = $this->superAdmin();
        $this->admin('taken');

        $this->actingAs($boss, 'sanctum')
            ->postJson('/api/admins', ['name' => 'X', 'username' => 'taken', 'password' => 'password1'])
            ->assertStatus(422)->assertJsonValidationErrors('username');

        $this->actingAs($boss, 'sanctum')
            ->postJson('/api/admins', ['name' => 'X', 'username' => 'newone', 'password' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_update_changes_data_and_ends_sessions_on_new_password(): void
    {
        $boss = $this->superAdmin();
        $admin = $this->admin();

        DB::table('sessions')->insert([
            'id' => 'session-1', 'user_id' => $admin->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($boss, 'sanctum')
            ->patchJson("/api/admins/{$admin->id}", ['name' => 'New Name', 'password' => 'newsecret99'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertTrue(Hash::check('newsecret99', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'session-1']);
    }

    public function test_deactivate_ends_sessions_and_blocks_login_then_activate_restores(): void
    {
        $boss = $this->superAdmin();
        $admin = $this->admin('worker');

        DB::table('sessions')->insert([
            'id' => 'session-2', 'user_id' => $admin->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($boss, 'sanctum')->postJson("/api/admins/{$admin->id}/deactivate")
            ->assertOk()->assertJsonPath('data.status', 'deactivated');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-2']);

        $this->withHeaders(['Origin' => 'http://localhost:3000'])
            ->postJson('/api/admin/login', ['username' => 'worker', 'password' => 'adminpass123'])
            ->assertStatus(401);

        $this->actingAs($boss, 'sanctum')->postJson("/api/admins/{$admin->id}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->withHeaders(['Origin' => 'http://localhost:3000'])
            ->postJson('/api/admin/login', ['username' => 'worker', 'password' => 'adminpass123'])
            ->assertOk();
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $boss = $this->superAdmin();
        $admin = $this->admin('gone');

        $this->actingAs($boss, 'sanctum')->deleteJson("/api/admins/{$admin->id}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $admin->id]);
        $this->actingAs($boss, 'sanctum')->getJson("/api/admins/{$admin->id}")->assertNotFound();
    }

    public function test_the_super_admin_and_testers_cannot_be_touched(): void
    {
        $boss = $this->superAdmin();
        $tester = User::create(['role' => 'tester', 'status' => 'active', 'name' => 'T',
            'phone' => '+967777000002', 'tester_number' => 1]);

        foreach ([$boss->id, $tester->id] as $id) {
            $this->actingAs($boss, 'sanctum')->postJson("/api/admins/{$id}/deactivate")->assertNotFound();
            $this->actingAs($boss, 'sanctum')->deleteJson("/api/admins/{$id}")->assertNotFound();
            $this->actingAs($boss, 'sanctum')->patchJson("/api/admins/{$id}", ['name' => 'Hacked'])->assertNotFound();
        }

        $this->assertSame('active', $boss->fresh()->status);
    }
}