<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => env('SUPERADMIN_USERNAME', 'superadmin')],
            [
                'role' => Role::SuperAdmin,
                'name' => 'Super Admin',
                'password' => env('SUPERADMIN_PASSWORD', 'change-me-now'),
            ]
        );

        // The tester number counter always has exactly one row
        if (! DB::table('tester_counter')->exists()) {
            DB::table('tester_counter')->insert(['last_number' => 0]);
        }
    }
}