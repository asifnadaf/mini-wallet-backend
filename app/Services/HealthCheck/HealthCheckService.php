<?php

namespace App\Services\HealthCheck;

use App\Contracts\HealthCheckable;
use App\Services\HealthCheck\HealthCheckers\ComputeHealthChecker;
use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use Illuminate\Support\Collection;

class HealthCheckService
{
    protected array $checkers = [
        ComputeHealthChecker::class,
        MySQLHealthChecker::class,
    ];

    public function getHealthStatus(): Collection
    {
        $results = collect();

        foreach ($this->checkers as $checkerClass) {
            /** @var HealthCheckable $checker */
            $checker = app($checkerClass);

            $results->put(
                $checker->getName(),
                $checker->check()
            );
        }

        return $results;
    }

    public function addChecker(string $checkerClass): self
    {
        if (!in_array($checkerClass, $this->checkers)) {
            $this->checkers[] = $checkerClass;
        }

        return $this;
    }
}
