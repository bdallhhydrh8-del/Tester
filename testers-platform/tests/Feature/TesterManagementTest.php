<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TesterManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['role' => 'admin', 'status' => 'active', 'name' => 'Admin',
            'username' => 'adm', 'password' => 'adminpass123']);
    }

    private function tester(int $number, string $name, string $phone): User
    {
        return User::create(['role' => 'tester', 'status' => 'active', 'name' => $name,
            'phone' => $phone, 'tester_number' => $number]);
    }

       public function test_only_admins_can_see_testers(): void
    {
        // Not logged in: must be checked before any actingAs()
        $this->getJson('/api/testers')->assertStatus(401);

        $tester = $this->tester(1, 'Ali', '+967777000001');

        $this->actingAs($tester, 'sanctum')->getJson('/api/testers')->assertStatus(403);
        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/testers')->assertOk();
    }

    public function test_list_and_search(): void
    {
        $this->tester(1, 'Ali Saleh', '+967777000001');
        $this->tester(2, 'Sara Omar', '+967711000002');
        $this->tester(3, 'Omar Nasser', '+967733000003');
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson('/api/testers')->assertJsonCount(3, 'data');
        $this->actingAs($admin, 'sanctum')->getJson('/api/testers?search=Omar')->assertJsonCount(2, 'data');
        $this->actingAs($admin, 'sanctum')->getJson('/api/testers?search=7110')->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'sanctum')->getJson('/api/testers?search=3')
            ->assertJsonPath('data.0.tester_number', 3);
    }

    public function test_show_includes_the_links_the_tester_joined(): void
    {
        $admin = $this->admin();
        $tester = $this->tester(1, 'Ali', '+967777000001');

        $link = Invitation::create(['name' => 'Week 1', 'token' => str_repeat('a', 40), 'created_by' => $admin->id]);
        DB::table('invitation_testers')->insert([
            'invitation_id' => $link->id, 'tester_id' => $tester->id, 'joined_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')->getJson("/api/testers/{$tester->id}")
            ->assertOk()
            ->assertJsonPath('data.tester_number', 1)
            ->assertJsonPath('data.invitations.0.name', 'Week 1');
    }

    public function test_update_name_and_phone(): void
    {
        $admin = $this->admin();
        $tester = $this->tester(1, 'Ali', '+967777000001');
        $other = $this->tester(2, 'Sara', '+967777000002');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/testers/{$tester->id}", ['name' => 'Ali Saleh', 'phone' => '0771234567'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ali Saleh')
            ->assertJsonPath('data.phone', '+967771234567')
            ->assertJsonPath('data.tester_number', 1);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/testers/{$tester->id}", ['phone' => $other->phone])
            ->assertStatus(409)->assertJsonPath('code', 'phone_registered');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/testers/{$tester->id}", ['phone' => '12345'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_phone');

        // Keeping the same number is allowed
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/testers/{$tester->id}", ['phone' => '777123456'])
            ->assertOk();
    }

    public function test_admins_are_not_returned_as_testers(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson("/api/testers/{$admin->id}")->assertNotFound();
    }
}