<?php

namespace App\Services\HealthCheck\HealthCheckers;

use App\Contracts\HealthCheckable;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\Log;

class MailHogHealthChecker implements HealthCheckable
{
    public function check(): ServerStatus
    {
        try {
            // Check if MailHog SMTP port is accessible
            $connection = @fsockopen('mailhog', 1025, $errno, $errstr, 5);

            if ($connection) {
                fclose($connection);
                return ServerStatus::UP;
            }

            return ServerStatus::DOWN;
        } catch (\Exception $exception) {
            Log::error('MailHog health check failed: ' . $exception->getMessage());
            return ServerStatus::DOWN;
        }
    }

    public function getName(): string
    {
        return 'mailhog_server';
    }
}
