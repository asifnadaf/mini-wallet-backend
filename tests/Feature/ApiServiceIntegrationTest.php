<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheckResponseFormatter;
use App\Services\ApiResponseService;
use App\Contracts\ApiResponseInterface;
use App\Contracts\HealthCheckable;
use App\Services\HealthCheck\HealthCheckers\ComputeHealthChecker;
use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class ApiServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test complete service layer integration
     */
    public function test_complete_service_layer_integration()
    {
        // Test HealthCheckService integration
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $healthStatus);
        $this->assertTrue($healthStatus->has('compute_server'));
        $this->assertTrue($healthStatus->has('mysql_server'));

        // Test HealthCheckResponseFormatter integration
        $formatter = app(HealthCheckResponseFormatter::class);
        $response = $formatter->format($healthStatus);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // Test ApiResponseService integration
        $apiResponseService = app(ApiResponseInterface::class);
        $this->assertInstanceOf(ApiResponseService::class, $apiResponseService);
    }

    /**
     * Test health checker service integration
     */
    public function test_health_checker_service_integration()
    {
        $healthCheckService = app(HealthCheckService::class);

        // Test that all checkers are properly registered
        $healthStatus = $healthCheckService->getHealthStatus();

        $this->assertCount(2, $healthStatus);
        $this->assertTrue($healthStatus->has('compute_server'));
        $this->assertTrue($healthStatus->has('mysql_server'));

        // Test individual checker integration
        $computeChecker = app(ComputeHealthChecker::class);
        $this->assertInstanceOf(HealthCheckable::class, $computeChecker);
        $this->assertEquals(ServerStatus::UP, $computeChecker->check());

        $mysqlChecker = app(MySQLHealthChecker::class);
        $this->assertInstanceOf(HealthCheckable::class, $mysqlChecker);
        $this->assertInstanceOf(ServerStatus::class, $mysqlChecker->check());
    }

    /**
     * Test service dependency resolution
     */
    public function test_service_dependency_resolution()
    {
        // Test that all services can be resolved from container
        $services = [
            HealthCheckService::class,
            HealthCheckResponseFormatter::class,
            ApiResponseInterface::class,
            ApiResponseService::class,
            ComputeHealthChecker::class,
            MySQLHealthChecker::class,
        ];

        foreach ($services as $service) {
            $instance = app($service);
            $this->assertNotNull($instance, "Service {$service} should be resolvable");
            $this->assertIsObject($instance, "Service {$service} should be an object");
        }
    }

    /**
     * Test service method integration
     */
    public function test_service_method_integration()
    {
        // Test HealthCheckService methods
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $healthStatus);

        // Test adding new checker
        $healthCheckService->addChecker(ComputeHealthChecker::class);
        $newHealthStatus = $healthCheckService->getHealthStatus();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $newHealthStatus);

        // Test HealthCheckResponseFormatter methods
        $formatter = app(HealthCheckResponseFormatter::class);
        $response = $formatter->format($healthStatus);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);
    }

    /**
     * Test service error handling integration
     */
    public function test_service_error_handling_integration()
    {
        // Test with database failure
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->andThrow(new \Exception('Database connection failed'));

        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        // Should handle database failure gracefully
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $healthStatus);
        $this->assertTrue($healthStatus->has('mysql_server'));
        $this->assertEquals(ServerStatus::DOWN, $healthStatus->get('mysql_server'));
    }

    /**
     * Test service performance integration
     */
    public function test_service_performance_integration()
    {
        $startTime = microtime(true);

        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $formatter = app(HealthCheckResponseFormatter::class);
        $response = $formatter->format($healthStatus);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertLessThan(1, $executionTime, 'Service integration should be fast');
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
    }

    /**
     * Test service memory usage integration
     */
    public function test_service_memory_usage_integration()
    {
        $initialMemory = memory_get_usage();

        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $formatter = app(HealthCheckResponseFormatter::class);
        $response = $formatter->format($healthStatus);

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        $this->assertLessThan(1024 * 1024, $memoryUsed, 'Service integration should be memory efficient');
    }

    /**
     * Test service configuration integration
     */
    public function test_service_configuration_integration()
    {
        // Test that services are properly configured
        $healthCheckService = app(HealthCheckService::class);
        $this->assertInstanceOf(HealthCheckService::class, $healthCheckService);

        $formatter = app(HealthCheckResponseFormatter::class);
        $this->assertInstanceOf(HealthCheckResponseFormatter::class, $formatter);

        $apiResponseService = app(ApiResponseInterface::class);
        $this->assertInstanceOf(ApiResponseService::class, $apiResponseService);
    }

    /**
     * Test service logging integration
     */
    public function test_service_logging_integration()
    {
        // Test that services work without logging errors
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $formatter = app(HealthCheckResponseFormatter::class);
        $response = $formatter->format($healthStatus);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test service data flow integration
     */
    public function test_service_data_flow_integration()
    {
        // Test complete data flow through services
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $healthStatus);
        $this->assertCount(2, $healthStatus);

        $formatter = app(HealthCheckResponseFormatter::class);
        $response = $formatter->format($healthStatus);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);

        $responseData = $response->getData(true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('services', $responseData['data']);
        $this->assertCount(2, $responseData['data']['services']);
    }

    /**
     * Test service exception handling integration
     */
    public function test_service_exception_handling_integration()
    {
        // Test with service exception
        $this->app->bind(HealthCheckService::class, function () {
            $mock = Mockery::mock(HealthCheckService::class);
            $mock->shouldReceive('getHealthStatus')
                ->andThrow(new \Exception('Service unavailable'));
            return $mock;
        });

        $healthCheckService = app(HealthCheckService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service unavailable');

        $healthCheckService->getHealthStatus();
    }

    /**
     * Test service interface compliance
     */
    public function test_service_interface_compliance()
    {
        // Test HealthCheckable interface compliance
        $computeChecker = app(ComputeHealthChecker::class);
        $this->assertInstanceOf(HealthCheckable::class, $computeChecker);
        $this->assertTrue(method_exists($computeChecker, 'check'));
        $this->assertTrue(method_exists($computeChecker, 'getName'));

        $mysqlChecker = app(MySQLHealthChecker::class);
        $this->assertInstanceOf(HealthCheckable::class, $mysqlChecker);
        $this->assertTrue(method_exists($mysqlChecker, 'check'));
        $this->assertTrue(method_exists($mysqlChecker, 'getName'));

        // Test ApiResponseInterface compliance
        $apiResponseService = app(ApiResponseInterface::class);
        $this->assertInstanceOf(ApiResponseInterface::class, $apiResponseService);
        $this->assertTrue(method_exists($apiResponseService, 'success'));
        $this->assertTrue(method_exists($apiResponseService, 'error'));
    }

    /**
     * Test service resolution behavior
     */
    public function test_service_resolution_behavior()
    {
        $service1 = app(HealthCheckService::class);
        $service2 = app(HealthCheckService::class);

        // Services should be the same type (but may not be same instance)
        $this->assertInstanceOf(HealthCheckService::class, $service1);
        $this->assertInstanceOf(HealthCheckService::class, $service2);

        $formatter1 = app(HealthCheckResponseFormatter::class);
        $formatter2 = app(HealthCheckResponseFormatter::class);

        $this->assertInstanceOf(HealthCheckResponseFormatter::class, $formatter1);
        $this->assertInstanceOf(HealthCheckResponseFormatter::class, $formatter2);
    }

    /**
     * Test service method chaining
     */
    public function test_service_method_chaining()
    {
        $healthCheckService = app(HealthCheckService::class);

        // Test method chaining
        $result = $healthCheckService
            ->addChecker(ComputeHealthChecker::class)
            ->getHealthStatus();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }
}
