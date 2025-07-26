<?php

namespace App\Providers;

use App\Contracts\GeminiClientInterface;
use App\Services\GeminiClientWrapper;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GeminiClientInterface::class, GeminiClientWrapper::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
