<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ApiResponseInterface;
use App\Services\ApiResponseService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ApiResponseInterface::class, ApiResponseService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
