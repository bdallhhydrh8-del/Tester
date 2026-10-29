<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Ai\SummaryGenerator::class, \App\Services\Ai\FakeSummaryGenerator::class);
        $this->app->bind(\App\Services\Results\ProjectNotesSource::class, \App\Services\Results\EloquentProjectNotesSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
