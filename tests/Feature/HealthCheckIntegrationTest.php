<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheckResponseFormatter;
use App\Services\ApiResponseService;
use App\Contracts\ApiResponseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class HealthCheckIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Integration test: Test the complete request/response cycle
     */
    public function test_complete_request_response_cycle()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJson([
                'success' => true
            ]);

        // Verify the response can be parsed as JSON
        $responseData = $response->json();
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('success', $responseData);
    }

    /**
     * Integration test: Test service dependency injection
     */
    public function test_service_dependency_injection()
    {
        // Verify that all services are properly bound in the container
        $this->assertInstanceOf(HealthCheckService::class, app(HealthCheckService::class));
        $this->assertInstanceOf(HealthCheckResponseFormatter::class, app(HealthCheckResponseFormatter::class));
        $this->assertInstanceOf(ApiResponseInterface::class, app(ApiResponseInterface::class));
    }

    /**
     * Integration test: Test route registration
     */
    public function test_route_registration()
    {
        $routes = app('router')->getRoutes();
        $healthCheckRoute = null;

        foreach ($routes as $route) {
            if ($route->uri() === 'api/v1/health-check') {
                $healthCheckRoute = $route;
                break;
            }
        }

        $this->assertNotNull($healthCheckRoute, 'Health check route is not registered');
        $this->assertEquals('GET', $healthCheckRoute->methods()[0]);
        $this->assertEquals('api.', $healthCheckRoute->getName());
    }

    /**
     * Integration test: Test middleware and authentication
     */
    public function test_health_check_does_not_require_authentication()
    {
        // Health check should be accessible without authentication
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);
    }

    /**
     * Integration test: Test error handling across the stack
     */
    public function test_error_handling_across_stack()
    {
        // Mock a deep exception in the service layer
        $this->app->bind(HealthCheckService::class, function () {
            $mock = Mockery::mock(HealthCheckService::class);
            $mock->shouldReceive('getHealthStatus')
                ->andThrow(new \Exception('Deep service error'));
            return $mock;
        });

        Log::shouldReceive('error')
            ->with('Health check failed: Deep service error')
            ->once();

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Unable to complete health check',
                'errors' => 'Deep service error'
            ]);
    }

    /**
     * Integration test: Test response formatting consistency
     */
    public function test_response_formatting_consistency()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'services' => [
                        '*' => [
                            'service',
                            'status',
                            'last_checked'
                        ]
                    ]
                ]
            ]);

        // Verify that the response follows the API response format
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);
    }

    /**
     * Integration test: Test database connectivity
     */
    public function test_database_connectivity_integration()
    {
        // Test that the database connection is working
        $this->assertDatabaseConnection();

        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Verify that mysql_server status is 'up' when database is accessible
        $services = $response->json('data.services');
        $mysqlService = collect($services)->firstWhere('service', 'mysql_server');
        $this->assertEquals('up', $mysqlService['status']);
    }

    /**
     * Integration test: Test service health checkers integration
     */
    public function test_service_health_checkers_integration()
    {
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        // Verify that all expected services are checked
        $this->assertTrue($healthStatus->has('compute_server'));
        $this->assertTrue($healthStatus->has('mysql_server'));

        // Verify that all services return ServerStatus enum values
        foreach ($healthStatus as $status) {
            $this->assertInstanceOf(ServerStatus::class, $status);
        }
    }

    /**
     * Integration test: Test response time and performance
     */
    public function test_response_time_performance()
    {
        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/health-check');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Health check should respond within 2 seconds
        $this->assertLessThan(2, $responseTime, 'Health check response time is too slow');
    }

    /**
     * Integration test: Test memory usage
     */
    public function test_memory_usage()
    {
        $initialMemory = memory_get_usage();

        $response = $this->getJson('/api/v1/health-check');

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        $response->assertStatus(200);

        // Health check should not use excessive memory (less than 1MB)
        $this->assertLessThan(1024 * 1024, $memoryUsed, 'Health check used too much memory');
    }

    /**
     * Helper method to assert database connection
     */
    private function assertDatabaseConnection()
    {
        try {
            DB::select('SELECT 1');
            $this->assertTrue(true, 'Database connection is working');
        } catch (\Exception $e) {
            $this->fail('Database connection failed: ' . $e->getMessage());
        }
    }
}
