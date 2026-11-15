<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        // This prevents failures when creating issues from failed tasks; screenshots are not attached yet.
        $this->app->bind(
            \App\Contracts\FailedTaskIssueCreator::class,
            \App\Services\NullFailedTaskIssueCreator::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
