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
        $this->app->bind(\App\Services\Results\ResultsDataSource::class, \App\Services\Results\FixtureResultsDataSource::class);
        $this->app->bind(
            \App\Contracts\UploadService::class,
            \App\Services\ImageUploadService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
