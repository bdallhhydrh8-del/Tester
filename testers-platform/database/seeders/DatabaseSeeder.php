<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always: the SuperAdmin and the tester number counter
        $this->call(SuperAdminSeeder::class);

        // Demo data: local machines only, never on the real server
        if (app()->environment('local')) {
            $this->call(DemoUsersSeeder::class);   // Dev 1: admins, testers, invitation links

            // Each dev adds their own seeder here, in this order:
            // $this->call(DemoProjectsSeeder::class);       // Dev 2: projects, cards, settings
            // $this->call(DemoParticipationSeeder::class);  // Dev 3: accesses and responses
            // $this->call(DemoIssuesSeeder::class);         // Dev 4: issues
        }
    }
}