<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckDisk extends Command
{
    protected $signature = 'disk:check';

    protected $description = 'Warn when the free disk space is low';

    public function handle(): int
    {
        $path = config('tester.disk_path');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || ! $total) {
            $this->error("Cannot read the disk space of {$path}");

            return self::FAILURE;
        }

        $freePercent = round($free / $total * 100, 1);
        $low = $freePercent < config('tester.disk_alert_free_percent');

        // Dev 4 can read this from the cache to show the alert on the overview page
        Cache::forever('disk_status', [
            'free_percent' => $freePercent,
            'low' => $low,
            'checked_at' => now()->toIso8601String(),
        ]);

        if ($low) {
            Log::warning("Low disk space: only {$freePercent}% free on {$path}");
            $this->warn("Low disk space: {$freePercent}% free");

            return self::FAILURE;
        }

        $this->info("Disk space OK: {$freePercent}% free");

        return self::SUCCESS;
    }
}