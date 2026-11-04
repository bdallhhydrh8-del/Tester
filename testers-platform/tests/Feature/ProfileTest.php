<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['role' => 'admin', 'status' => 'active', 'name' => 'Sami',
            'username' => 'sami', 'password' => 'adminpass123']);
    }

    public function test_only_admins_can_use_the_profile(): void
    {
        // Not logged in: must be checked before any actingAs()
        $this->getJson('/api/profile')->assertStatus(401);

        $tester = User::create(['role' => 'tester', 'status' => 'active', 'name' => 'T',
            'phone' => '+967777000001', 'tester_number' => 1]);

        $this->actingAs($tester, 'sanctum')->getJson('/api/profile')->assertStatus(403);
        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Sami')
            ->assertJsonPath('data.username', 'sami')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonMissingPath('data.password');
    }

    public function test_admin_changes_name_and_password_and_other_sessions_end(): void
    {
        $admin = $this->admin();

        DB::table('sessions')->insert([
            'id' => 'other-device', 'user_id' => $admin->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/profile', [
                'name' => 'Sami Ahmed',
                'current_password' => 'adminpass123',
                'password' => 'newpass456',
                'password_confirmation' => 'newpass456',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sami Ahmed');

        $this->assertTrue(Hash::check('newpass456', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_name_can_change_without_the_password(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/profile', ['name' => 'Only Name'])
            ->assertOk()->assertJsonPath('data.name', 'Only Name');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/profile', [
                'current_password' => 'not-my-password1',
                'password' => 'newpass456',
                'password_confirmation' => 'newpass456',
            ])
            ->assertStatus(422)->assertJsonPath('code', 'wrong_password');

        $this->assertTrue(Hash::check('adminpass123', $admin->fresh()->password));
    }

    public function test_password_rules_apply(): void
    {
        $admin = $this->admin();

        foreach (['onlyletters', '12345678', 'abc12'] as $weak) {
            $this->actingAs($admin, 'sanctum')
                ->patchJson('/api/profile', [
                    'current_password' => 'adminpass123', 'password' => $weak, 'password_confirmation' => $weak,
                ])
                ->assertStatus(422)->assertJsonValidationErrors('password');
        }

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/profile', [
                'current_password' => 'adminpass123', 'password' => 'newpass456', 'password_confirmation' => 'different789',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_super_admin_can_rename_but_not_change_the_password(): void
    {
        $boss = User::create(['role' => 'super_admin', 'status' => 'active', 'name' => 'Boss',
            'username' => 'boss', 'password' => 'bosspass123']);

        $this->actingAs($boss, 'sanctum')
            ->patchJson('/api/profile', ['name' => 'The Boss'])
            ->assertOk()->assertJsonPath('data.name', 'The Boss');

        $this->actingAs($boss, 'sanctum')
            ->patchJson('/api/profile', [
                'current_password' => 'bosspass123', 'password' => 'newpass456', 'password_confirmation' => 'newpass456',
            ])
            ->assertStatus(403)->assertJsonPath('code', 'superadmin_password_locked');

        $this->assertTrue(Hash::check('bosspass123', $boss->fresh()->password));
    }
}