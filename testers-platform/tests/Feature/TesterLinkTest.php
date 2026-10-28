<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TesterLinkTest extends TestCase
{
    use RefreshDatabase;

    private array $frontend = ['Origin' => 'http://localhost:3000'];

    private function makeLink(array $overrides = []): Invitation
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin1'],
            ['role' => 'admin', 'status' => 'active', 'name' => 'Admin', 'password' => 'secret123']
        );

        return Invitation::create(array_merge([
            'name' => 'Week 1',
            'token' => Str::random(40),
            'created_by' => $admin->id,
        ], $overrides));
    }

    private function postApi(string $url, array $data)
    {
        return $this->withHeaders($this->frontend)->postJson($url, $data);
    }

    public function test_link_states(): void
    {
        $active = $this->makeLink();
        $stopped = $this->makeLink(['stopped_at' => now()]);
        $expired = $this->makeLink(['expires_at' => now()->subDay()]);

        $this->getJson("/api/links/{$active->token}")->assertOk()->assertJsonPath('data.state', 'active');
        $this->getJson("/api/links/{$stopped->token}")->assertOk()->assertJsonPath('data.state', 'stopped');
        $this->getJson("/api/links/{$expired->token}")->assertOk()->assertJsonPath('data.state', 'expired');
        $this->getJson('/api/links/does-not-exist')->assertOk()->assertJsonPath('data.state', 'unknown');
    }

    public function test_register_creates_a_numbered_tester_and_logs_in(): void
    {
        $link = $this->makeLink();

        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '0777123456'])
            ->assertCreated()
            ->assertJsonPath('data.user.phone', '+967777123456')
            ->assertJsonPath('data.user.tester_number', 1)
            ->assertJsonPath('data.next.to', 'home'); // this link has no projects yet

        $this->assertAuthenticated('web');

        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Sara', 'phone' => '771234567'])
            ->assertCreated()
            ->assertJsonPath('data.user.tester_number', 2);
    }

    public function test_known_phone_gets_a_login_message(): void
    {
        $link = $this->makeLink();
        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '777123456'])->assertCreated();

        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali again', 'phone' => '+967777123456'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'phone_registered');
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $link = $this->makeLink();

        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '12345'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_phone');
    }

    public function test_stopped_and_expired_links_block_register_and_login(): void
    {
        $stopped = $this->makeLink(['stopped_at' => now()]);
        $expired = $this->makeLink(['expires_at' => now()->subDay()]);

        foreach ([[$stopped, 'link_stopped'], [$expired, 'link_expired']] as [$link, $code]) {
            $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '777123456'])
                ->assertStatus(409)->assertJsonPath('code', $code);

            $this->postApi("/api/links/{$link->token}/login", ['phone' => '777123456'])
                ->assertStatus(409)->assertJsonPath('code', $code);
        }

        $this->postApi('/api/links/nope/register', ['name' => 'Ali', 'phone' => '777123456'])->assertNotFound();
    }

    public function test_existing_tester_logs_in_through_a_link(): void
    {
        $link = $this->makeLink();
        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '777123456'])->assertCreated();

        $this->postApi("/api/links/{$link->token}/login", ['phone' => '0777123456'])
            ->assertOk()
            ->assertJsonPath('data.user.tester_number', 1);

        // Joining twice must not create a second row
        $this->assertDatabaseCount('invitation_testers', 1);
    }

    public function test_unknown_phone_is_told_to_register_and_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postApi('/api/tester/login', ['phone' => '777999888'])
                ->assertNotFound()
                ->assertJsonPath('code', 'not_registered');
        }

        $this->postApi('/api/tester/login', ['phone' => '777999888'])->assertStatus(429);
    }

    public function test_tester_cannot_use_admin_routes(): void
    {
        $link = $this->makeLink();
        $this->postApi("/api/links/{$link->token}/register", ['name' => 'Ali', 'phone' => '777123456'])->assertCreated();

        $this->actingAs(User::where('phone', '+967777123456')->first(), 'sanctum')
            ->getJson('/api/testers')
            ->assertStatus(403);
    }
}
