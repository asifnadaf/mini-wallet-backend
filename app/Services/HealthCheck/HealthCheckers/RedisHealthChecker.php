<?php

namespace App\Services\HealthCheck\HealthCheckers;

use App\Contracts\HealthCheckable;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisHealthChecker implements HealthCheckable
{
    public function check(): ServerStatus
    {
        try {
            Redis::ping();
            return ServerStatus::UP;
        } catch (\Exception $exception) {
            Log::error('Redis health check failed: ' . $exception->getMessage());
            return ServerStatus::DOWN;
        }
    }

    public function getName(): string
    {
        return 'redis_server';
    }
}
