<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Only creates the row if none exists, so re-running never resets the admin's changes
        Setting::current();
    }
}