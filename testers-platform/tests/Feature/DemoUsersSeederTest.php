<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $this->assertSame(15, User::where('role', 'tester')->count());
        $this->assertSame(2, User::where('role', 'admin')->count());
        $this->assertSame(4, DB::table('invitations')->count());
        $this->assertSame(15, DB::table('invitation_testers')->count());
        $this->assertSame(15, (int) DB::table('tester_counter')->value('last_number'));
        $this->assertSame(15, User::where('role', 'tester')->max('tester_number'));
    }
}