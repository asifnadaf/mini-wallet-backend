<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ApiResponseInterface;
use App\Services\ApiResponseService;
use App\Strategies\Token\TokenSenderStrategy;
use App\Strategies\Token\EmailTokenStrategy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ApiResponseInterface::class, ApiResponseService::class);
        $this->app->bind(TokenSenderStrategy::class, function ($app, $params) {
            $type = $params['type'] ?? 'email';

            return match ($type) {
                'email' => $app->make(EmailTokenStrategy::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
