<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    // Sanctum only starts a session for requests that come from a "stateful" domain
    private array $frontend = ['Origin' => 'http://localhost:3000'];

    public function test_admin_can_log_in_and_wrong_password_fails(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $this->withHeaders($this->frontend)
            ->postJson('/api/admin/login', ['username' => 'superadmin', 'password' => 'wrong'])
            ->assertStatus(401);

        $this->withHeaders($this->frontend)
            ->postJson('/api/admin/login', [
                'username' => 'superadmin',
                'password' => env('SUPERADMIN_PASSWORD', 'change-me-now'),
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'super_admin');
    }

    public function test_me_and_role_middleware(): void
    {
      $admin = User::create([
    'role' => 'admin', 'status' => 'active', 'name' => 'A', 'username' => 'a1', 'password' => 'secret123',
]);
$tester = User::create([
    'role' => 'tester', 'status' => 'active', 'name' => 'T', 'phone' => '+10000000001', 'tester_number' => 1,
]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/me')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/testers')->assertOk();
        $this->actingAs($tester, 'sanctum')->getJson('/api/testers')->assertStatus(403);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->seed(SuperAdminSeeder::class);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->frontend)
                ->postJson('/api/admin/login', ['username' => 'superadmin', 'password' => 'bad'])
                ->assertStatus(401);
        }

        $this->withHeaders($this->frontend)
            ->postJson('/api/admin/login', ['username' => 'superadmin', 'password' => 'bad'])
            ->assertStatus(429);
    }
}