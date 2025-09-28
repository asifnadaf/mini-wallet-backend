<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ApiResponseInterface;
use App\Services\ApiResponseService;
use App\Strategies\Token\TokenSenderStrategy;
use App\Strategies\Token\EmailTokenStrategy;
use App\Strategies\Token\PasswordTokenStrategy;
use App\Repositories\EmailVerificationTokenRepository;
use App\Repositories\PasswordResetTokenRepository;
use App\Factories\TokenFactory;
use App\Services\EmailTokenService;
use App\Services\ForgotPasswordService;

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
                'password' => $app->make(PasswordTokenStrategy::class),
                'email' => $app->make(EmailTokenStrategy::class),
            };
        });

        $this->app->when(ForgotPasswordService::class)
            ->needs(TokenSenderStrategy::class)
            ->give(PasswordTokenStrategy::class);


        // supporting services
        $this->app->singleton(EmailVerificationTokenRepository::class);
        $this->app->singleton(PasswordResetTokenRepository::class);
        $this->app->singleton(TokenFactory::class);
        $this->app->singleton(EmailTokenService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
