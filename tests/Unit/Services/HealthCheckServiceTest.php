<?php

namespace Tests\Unit\Services;

use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheck\HealthCheckers\ComputeHealthChecker;
use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use App\Enums\ServerStatus;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Mockery;

class HealthCheckServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_health_status_returns_collection()
    {
        $service = new HealthCheckService();
        $result = $service->getHealthStatus();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_health_status_includes_all_checkers()
    {
        $service = new HealthCheckService();
        $result = $service->getHealthStatus();

        $this->assertTrue($result->has('compute_server'));
        $this->assertTrue($result->has('mysql_server'));
    }

    public function test_get_health_status_returns_server_status_enum()
    {
        $service = new HealthCheckService();
        $result = $service->getHealthStatus();

        $this->assertInstanceOf(ServerStatus::class, $result->get('compute_server'));
        $this->assertInstanceOf(ServerStatus::class, $result->get('mysql_server'));
    }

    public function test_add_checker_adds_new_checker()
    {
        $service = new HealthCheckService();
        $initialCount = count($service->getHealthStatus());

        // Create a mock checker
        $mockChecker = Mockery::mock('App\Contracts\HealthCheckable');
        $mockChecker->shouldReceive('getName')->andReturn('test_service');
        $mockChecker->shouldReceive('check')->andReturn(ServerStatus::UP);

        // Register the mock in the container
        $this->app->instance('TestHealthChecker', $mockChecker);

        $service->addChecker('TestHealthChecker');
        $result = $service->getHealthStatus();

        $this->assertTrue($result->has('test_service'));
    }

    public function test_add_checker_does_not_duplicate_existing_checker()
    {
        $service = new HealthCheckService();
        $service->addChecker(ComputeHealthChecker::class);

        $result = $service->getHealthStatus();
        $computeServices = $result->filter(function ($value, $key) {
            return $key === 'compute_server';
        });

        $this->assertCount(1, $computeServices);
    }
}
