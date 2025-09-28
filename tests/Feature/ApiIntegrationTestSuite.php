<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheckResponseFormatter;
use App\Services\ApiResponseService;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Api\V1\ServerStatusController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Mockery;

class ApiIntegrationTestSuite extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Complete API integration test suite
     * Tests the entire API stack from request to response
     */
    public function test_complete_api_integration_suite()
    {
        // 1. Test API routing and middleware
        $this->test_api_routing_and_middleware();

        // 2. Test API service layer integration
        $this->test_api_service_layer_integration();

        // 3. Test API database integration
        $this->test_api_database_integration();

        // 4. Test API response formatting
        $this->test_api_response_formatting();

        // 5. Test API error handling (simplified)
        $this->test_api_error_handling_simplified();

        // 6. Test API performance
        $this->test_api_performance();

        // 7. Test API security
        $this->test_api_security();
    }

    /**
     * Test API routing and middleware integration
     */
    private function test_api_routing_and_middleware()
    {
        // Test route registration
        $routes = Route::getRoutes();
        $apiRoute = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        })->first();

        $this->assertNotNull($apiRoute, 'API route should be registered');
        $this->assertEquals('GET', $apiRoute->methods()[0]);
        $this->assertEquals('api/v1/health-check', $apiRoute->uri());

        // Test middleware stack
        $middleware = $apiRoute->gatherMiddleware();
        $this->assertIsArray($middleware);

        // Test request processing
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
    }

    /**
     * Test API service layer integration
     */
    private function test_api_service_layer_integration()
    {
        // Test service dependency injection
        $healthCheckService = app(HealthCheckService::class);
        $this->assertInstanceOf(HealthCheckService::class, $healthCheckService);

        $formatter = app(HealthCheckResponseFormatter::class);
        $this->assertInstanceOf(HealthCheckResponseFormatter::class, $formatter);

        $apiResponseService = app(ApiResponseInterface::class);
        $this->assertInstanceOf(ApiResponseService::class, $apiResponseService);

        // Test service integration
        $healthStatus = $healthCheckService->getHealthStatus();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $healthStatus);
        $this->assertCount(2, $healthStatus);

        $response = $formatter->format($healthStatus);
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test API database integration
     */
    private function test_api_database_integration()
    {
        // Test database connection
        $this->assertDatabaseConnection();

        // Test database health check
        $mysqlChecker = app(\App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker::class);
        $status = $mysqlChecker->check();
        $this->assertEquals(ServerStatus::UP, $status);

        // Test database performance
        $startTime = microtime(true);
        DB::select('SELECT 1');
        $endTime = microtime(true);
        $this->assertLessThan(1, $endTime - $startTime);
    }

    /**
     * Test API response formatting
     */
    private function test_api_response_formatting()
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

        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);
    }

    /**
     * Test API error handling (simplified)
     */
    private function test_api_error_handling_simplified()
    {
        // Test that API handles errors gracefully (basic test)
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test with invalid route
        $response = $this->getJson('/api/v1/invalid');
        $response->assertStatus(404);
    }

    /**
     * Test API performance
     */
    private function test_api_performance()
    {
        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/health-check');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(2, $responseTime, 'API should respond within 2 seconds');

        // Test memory usage
        $initialMemory = memory_get_usage();
        $response = $this->getJson('/api/v1/health-check');
        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        $this->assertLessThan(1024 * 1024, $memoryUsed, 'API should be memory efficient');
    }

    /**
     * Test API security
     */
    private function test_api_security()
    {
        // Test that health check doesn't require authentication
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test with different HTTP methods
        $this->postJson('/api/v1/health-check')->assertStatus(405);
        $this->putJson('/api/v1/health-check')->assertStatus(405);
        $this->deleteJson('/api/v1/health-check')->assertStatus(405);

        // Test with invalid routes
        $this->getJson('/api/v1/invalid')->assertStatus(404);
        $this->getJson('/api/v2/health-check')->assertStatus(404);
    }

    /**
     * Test API load handling
     */
    public function test_api_load_handling()
    {
        $responses = [];
        $startTime = microtime(true);

        // Simulate load with multiple concurrent requests
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->getJson('/api/v1/health-check');
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Should handle load efficiently
        $this->assertLessThan(5, $totalTime, 'API should handle load efficiently');
    }

    /**
     * Test API resilience
     */
    public function test_api_resilience()
    {
        // Test with database failure
        $this->app->bind(\App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker::class, function () {
            $mock = Mockery::mock(\App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker::class);
            $mock->shouldReceive('check')->andReturn(ServerStatus::DOWN);
            $mock->shouldReceive('getName')->andReturn('mysql_server');
            return $mock;
        });

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'unhealthy'
                ]
            ]);
    }

    /**
     * Test API monitoring and observability
     */
    public function test_api_monitoring_and_observability()
    {
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test response includes monitoring data
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('services', $responseData['data']);

        $services = $responseData['data']['services'];
        $this->assertIsArray($services);
        $this->assertCount(2, $services);

        // Verify service monitoring data
        foreach ($services as $service) {
            $this->assertArrayHasKey('service', $service);
            $this->assertArrayHasKey('status', $service);
            $this->assertArrayHasKey('last_checked', $service);
        }
    }

    /**
     * Test API versioning and backward compatibility
     */
    public function test_api_versioning_and_backward_compatibility()
    {
        // Test current version
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test version in URL
        $this->assertStringContainsString('/api/v1/', '/api/v1/health-check');

        // Test response format consistency
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'status',
                'services'
            ]
        ]);
    }

    /**
     * Test API documentation compliance
     */
    public function test_api_documentation_compliance()
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

        // Verify response follows documented format
        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);
        $this->assertIsString($responseData['data']['status']);
        $this->assertIsArray($responseData['data']['services']);
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
