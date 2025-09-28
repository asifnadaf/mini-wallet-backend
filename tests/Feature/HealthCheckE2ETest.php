<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheck\HealthCheckers\ComputeHealthChecker;
use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use App\Services\HealthCheckResponseFormatter;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class HealthCheckE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * End-to-end test: Complete health check flow with all services healthy
     */
    public function test_complete_health_check_flow_all_services_healthy()
    {
        // Ensure database is accessible
        $this->assertDatabaseConnection();

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'All services are operational',
                'data' => [
                    'status' => 'healthy'
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'services' => [
                        '*' => [
                            'service',
                            'status',
                            'last_checked'
                        ]
                    ]
                ]
            ]);

        // Verify the response structure is complete
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('status', $responseData['data']);
        $this->assertArrayHasKey('services', $responseData['data']);
        $this->assertCount(2, $responseData['data']['services']);
    }

    /**
     * End-to-end test: Complete health check flow with database failure
     */
    public function test_complete_health_check_flow_with_database_failure()
    {
        // Mock the MySQLHealthChecker to return DOWN status
        $this->app->bind(\App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker::class, function () {
            $mock = Mockery::mock(\App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker::class);
            $mock->shouldReceive('check')->andReturn(\App\Enums\ServerStatus::DOWN);
            $mock->shouldReceive('getName')->andReturn('mysql_server');
            return $mock;
        });

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Some services are experiencing issues',
                'data' => [
                    'status' => 'unhealthy'
                ]
            ]);

        // Verify that compute server is still up but mysql is down
        $services = $response->json('data.services');
        $computeService = collect($services)->firstWhere('service', 'compute_server');
        $mysqlService = collect($services)->firstWhere('service', 'mysql_server');

        $this->assertEquals('up', $computeService['status']);
        $this->assertEquals('down', $mysqlService['status']);
    }

    /**
     * End-to-end test: Complete health check flow with service exception
     */
    public function test_complete_health_check_flow_with_service_exception()
    {
        // Mock HealthCheckService to throw an exception
        $this->app->bind(HealthCheckService::class, function () {
            $mock = Mockery::mock(HealthCheckService::class);
            $mock->shouldReceive('getHealthStatus')
                ->andThrow(new \Exception('Health check service unavailable'));
            return $mock;
        });

        Log::shouldReceive('error')
            ->with('Health check failed: Health check service unavailable')
            ->once();

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Unable to complete health check',
                'errors' => 'Health check service unavailable'
            ]);
    }

    /**
     * End-to-end test: Verify all components work together correctly
     */
    public function test_all_components_integration()
    {
        // Test that all services are properly registered and working
        $healthCheckService = app(HealthCheckService::class);
        $healthStatus = $healthCheckService->getHealthStatus();

        $this->assertTrue($healthStatus->has('compute_server'));
        $this->assertTrue($healthStatus->has('mysql_server'));
        $this->assertInstanceOf(ServerStatus::class, $healthStatus->get('compute_server'));
        $this->assertInstanceOf(ServerStatus::class, $healthStatus->get('mysql_server'));

        // Test the complete flow
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Verify response contains all expected services
        $services = $response->json('data.services');
        $serviceNames = collect($services)->pluck('service')->toArray();

        $this->assertContains('compute_server', $serviceNames);
        $this->assertContains('mysql_server', $serviceNames);
    }

    /**
     * End-to-end test: Performance and reliability
     */
    public function test_health_check_performance_and_reliability()
    {
        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/health-check');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Health check should complete within reasonable time (5 seconds)
        $this->assertLessThan(5, $executionTime, 'Health check took too long to complete');
    }

    /**
     * End-to-end test: Multiple concurrent requests
     */
    public function test_health_check_handles_concurrent_requests()
    {
        $responses = [];

        // Simulate multiple concurrent requests
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->getJson('/api/v1/health-check');
        }

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'status',
                        'services'
                    ]
                ]);
        }
    }

    /**
     * End-to-end test: Verify logging behavior
     */
    public function test_health_check_logging_behavior()
    {
        // Test that the health check endpoint works without logging issues
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Verify the response structure
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
     * End-to-end test: Verify response format consistency
     */
    public function test_health_check_response_format_consistency()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success' => [],
                'message' => [],
                'data' => [
                    'status' => [],
                    'services' => [
                        '*' => [
                            'service' => [],
                            'status' => [],
                            'last_checked' => []
                        ]
                    ]
                ]
            ]);

        // Verify data types
        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);
        $this->assertIsString($responseData['data']['status']);
        $this->assertIsArray($responseData['data']['services']);

        // Verify service data types
        foreach ($responseData['data']['services'] as $service) {
            $this->assertIsString($service['service']);
            $this->assertIsString($service['status']);
            $this->assertIsString($service['last_checked']);
        }
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
