<?php

namespace App\Services\HealthCheck\HealthCheckers;

use App\Contracts\HealthCheckable;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MySQLHealthChecker implements HealthCheckable
{
    public function check(): ServerStatus
    {
        try {
            DB::select('SELECT 1');
            return ServerStatus::UP;
        } catch (\Exception $exception) {
            Log::error('MySQL health check failed: ' . $exception->getMessage());
            return ServerStatus::DOWN;
        }
    }

    public function getName(): string
    {
        return 'mysql_server';
    }
}
