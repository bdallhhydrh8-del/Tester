<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlatformCommandsTest extends TestCase
{
    public function test_disk_check_alerts_when_free_space_is_below_the_limit(): void
    {
        config(['tester.disk_alert_free_percent' => 101]); // always "low"

        $this->artisan('disk:check')->assertFailed();

        $this->assertTrue(Cache::get('disk_status')['low']);
    }

    public function test_disk_check_is_quiet_when_there_is_enough_space(): void
    {
        config(['tester.disk_alert_free_percent' => 0]); // never "low"

        $this->artisan('disk:check')->assertSuccessful();

        $this->assertFalse(Cache::get('disk_status')['low']);
    }

    public function test_backup_refuses_to_run_without_mysql(): void
    {
        // The tests run on SQLite, so the command must stop with an error
        $this->artisan('backup:run')->assertFailed();
    }
}