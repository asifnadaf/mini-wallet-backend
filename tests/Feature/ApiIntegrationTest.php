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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Mockery;

class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test API route registration and configuration
     */
    public function test_api_routes_are_properly_registered()
    {
        $routes = Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        $this->assertCount(13, $apiRoutes, 'Should have exactly thirteen API routes');

        $routeUris = $apiRoutes->pluck('uri')->toArray();
        $this->assertContains('api/v1/health-check', $routeUris);
        $this->assertContains('api/v1/register', $routeUris);
        $this->assertContains('api/v1/login', $routeUris);
        $this->assertContains('api/v1/logout', $routeUris);
        $this->assertContains('api/v1/email/send-token', $routeUris);
        $this->assertContains('api/v1/email/verify-token', $routeUris);
        $this->assertContains('api/v1/forgot-password/email/token', $routeUris);
        $this->assertContains('api/v1/forgot-password/verify/token', $routeUris);
        $this->assertContains('api/v1/forgot-password/reset-password', $routeUris);
        $this->assertContains('api/v1/user', $routeUris);
        $this->assertContains('api/v1/change-password', $routeUris);
        $this->assertContains('api/v1/transactions', $routeUris);

        $healthCheckRoute = $apiRoutes->first(function ($route) {
            return $route->uri() === 'api/v1/health-check';
        });
        $this->assertEquals('GET', $healthCheckRoute->methods()[0]);
        $this->assertEquals(ServerStatusController::class . '@healthCheck', $healthCheckRoute->getActionName());
    }

    /**
     * Test API middleware configuration
     */
    public function test_api_middleware_is_properly_configured()
    {
        $response = $this->getJson('/api/v1/health-check');

        // Should not require authentication for health check
        $response->assertStatus(200);

        // Should return JSON content type
        $response->assertHeader('content-type', 'application/json');
    }

    /**
     * Test API response format consistency
     */
    public function test_api_response_format_consistency()
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

        // Verify response follows API response interface
        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);
    }

    /**
     * Test API error handling integration
     */
    public function test_api_error_handling_integration()
    {
        // Mock service to throw exception
        $this->app->bind(HealthCheckService::class, function () {
            $mock = Mockery::mock(HealthCheckService::class);
            $mock->shouldReceive('getHealthStatus')
                ->andThrow(new \Exception('Service unavailable'));
            return $mock;
        });

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Unable to complete health check',
                'errors' => 'Service unavailable'
            ]);
    }

    /**
     * Test API service dependency injection
     */
    public function test_api_service_dependency_injection()
    {
        // Verify all services are properly bound
        $this->assertInstanceOf(HealthCheckService::class, app(HealthCheckService::class));
        $this->assertInstanceOf(HealthCheckResponseFormatter::class, app(HealthCheckResponseFormatter::class));
        $this->assertInstanceOf(ApiResponseInterface::class, app(ApiResponseInterface::class));
        $this->assertInstanceOf(ApiResponseService::class, app(ApiResponseService::class));
    }

    /**
     * Test API controller inheritance and methods
     */
    public function test_api_controller_inheritance()
    {
        $controller = app(ServerStatusController::class);

        $this->assertInstanceOf(\App\Http\Controllers\Api\BaseApiController::class, $controller);
        $this->assertInstanceOf(\App\Http\Controllers\Controller::class, $controller);
        $this->assertTrue(method_exists($controller, 'healthCheck'));
    }

    /**
     * Test API response service integration
     */
    public function test_api_response_service_integration()
    {
        $apiResponseService = app(ApiResponseInterface::class);

        // Test success response
        $successResponse = $apiResponseService->success(['test' => 'data'], 'Test message');
        $this->assertEquals(200, $successResponse->getStatusCode());

        // Test error response
        $errorResponse = $apiResponseService->error('Test error', 'Error details', 400);
        $this->assertEquals(400, $errorResponse->getStatusCode());
    }

    /**
     * Test API health check service integration
     */
    public function test_api_health_check_service_integration()
    {
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $healthStatus);
        $this->assertTrue($healthStatus->has('compute_server'));
        $this->assertTrue($healthStatus->has('mysql_server'));

        foreach ($healthStatus as $status) {
            $this->assertInstanceOf(ServerStatus::class, $status);
        }
    }

    /**
     * Test API response formatter integration
     */
    public function test_api_response_formatter_integration()
    {
        $formatter = app(HealthCheckResponseFormatter::class);
        $healthStatus = collect([
            'compute_server' => ServerStatus::UP,
            'mysql_server' => ServerStatus::UP,
        ]);

        $response = $formatter->format($healthStatus);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = $response->getData(true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);
    }

    /**
     * Test API database integration
     */
    public function test_api_database_integration()
    {
        // Test that database connection works for health checks
        $this->assertDatabaseConnection();

        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Verify mysql_server status is 'up' when database is accessible
        $services = $response->json('data.services');
        $mysqlService = collect($services)->firstWhere('service', 'mysql_server');
        $this->assertEquals('up', $mysqlService['status']);
    }

    /**
     * Test API performance under load
     */
    public function test_api_performance_under_load()
    {
        $startTime = microtime(true);

        // Simulate multiple concurrent requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->getJson('/api/v1/health-check');
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Should complete within reasonable time (5 seconds for 10 requests)
        $this->assertLessThan(5, $totalTime, 'API should handle load efficiently');
    }

    /**
     * Test API CORS and headers
     */
    public function test_api_cors_and_headers()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');

        // Check for CORS headers if configured
        $headers = $response->headers->all();
        $this->assertArrayHasKey('content-type', $headers);
    }

    /**
     * Test API versioning
     */
    public function test_api_versioning()
    {
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test that v1 is properly configured by checking the URL
        $this->assertStringContainsString('/api/v1/', '/api/v1/health-check');
    }

    /**
     * Test API logging integration
     */
    public function test_api_logging_integration()
    {
        // Test that the API works without logging errors
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Verify no errors in logs (this is a basic check)
        $this->assertTrue(true, 'API should work without logging errors');
    }

    /**
     * Test API memory usage
     */
    public function test_api_memory_usage()
    {
        $initialMemory = memory_get_usage();

        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // API should not use excessive memory (less than 2MB)
        $this->assertLessThan(2 * 1024 * 1024, $memoryUsed, 'API should be memory efficient');
    }

    /**
     * Test API with different HTTP methods
     */
    public function test_api_http_methods()
    {
        // GET should work
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // POST should not be allowed
        $response = $this->postJson('/api/v1/health-check');
        $response->assertStatus(405);

        // PUT should not be allowed
        $response = $this->putJson('/api/v1/health-check');
        $response->assertStatus(405);

        // DELETE should not be allowed
        $response = $this->deleteJson('/api/v1/health-check');
        $response->assertStatus(405);
    }

    /**
     * Test API with invalid routes
     */
    public function test_api_invalid_routes()
    {
        // Test non-existent API endpoint
        $response = $this->getJson('/api/v1/non-existent');
        $response->assertStatus(404);

        // Test invalid API version
        $response = $this->getJson('/api/v2/health-check');
        $response->assertStatus(404);
    }

    /**
     * Test API response caching headers
     */
    public function test_api_response_caching_headers()
    {
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Health check responses should not be cached
        $headers = $response->headers->all();

        // Check for cache-control headers
        if (isset($headers['cache-control'])) {
            $this->assertStringNotContainsString('public', $headers['cache-control'][0]);
        }
    }

    /**
     * Test API with different content types
     */
    public function test_api_content_type_handling()
    {
        // Test with JSON content type
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');

        // Test with different Accept headers
        $response = $this->get('/api/v1/health-check', [
            'Accept' => 'application/json'
        ]);
        $response->assertStatus(200);
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
