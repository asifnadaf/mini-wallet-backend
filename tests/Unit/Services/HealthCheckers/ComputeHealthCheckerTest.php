<?php

namespace Tests\Unit\Services\HealthCheckers;

use App\Services\HealthCheck\HealthCheckers\ComputeHealthChecker;
use App\Enums\ServerStatus;
use Tests\TestCase;

class ComputeHealthCheckerTest extends TestCase
{
    public function test_check_returns_up_status()
    {
        $checker = new ComputeHealthChecker();
        $result = $checker->check();

        $this->assertEquals(ServerStatus::UP, $result);
    }

    public function test_get_name_returns_correct_name()
    {
        $checker = new ComputeHealthChecker();
        $name = $checker->getName();

        $this->assertEquals('compute_server', $name);
    }

    public function test_implements_health_checkable_interface()
    {
        $checker = new ComputeHealthChecker();

        $this->assertInstanceOf('App\Contracts\HealthCheckable', $checker);
    }
}
