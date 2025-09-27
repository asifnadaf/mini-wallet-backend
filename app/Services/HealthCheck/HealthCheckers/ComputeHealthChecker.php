<?php

namespace App\Services\HealthCheck\HealthCheckers;

use App\Contracts\HealthCheckable;
use App\Enums\ServerStatus;

class ComputeHealthChecker implements HealthCheckable
{
    public function check(): ServerStatus
    {
        return ServerStatus::UP;
    }

    public function getName(): string
    {
        return 'compute_server';
    }
}
