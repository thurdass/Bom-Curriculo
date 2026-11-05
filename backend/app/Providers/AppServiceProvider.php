<?php

namespace App\Providers;

use App\Services\Resume\OllamaResumeGenerator;
use App\Services\Resume\ResumeGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResumeGenerator::class, OllamaResumeGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
