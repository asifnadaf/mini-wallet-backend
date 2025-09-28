<?php

namespace Tests\Feature\Api;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheckResponseFormatter;
use App\Services\HealthCheck\HealthCheckers\ComputeHealthChecker;
use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_health_check_endpoint_returns_successful_response()
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
    }

    public function test_health_check_endpoint_returns_healthy_status()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy'
                ]
            ]);
    }

    public function test_health_check_endpoint_includes_all_services()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200);

        $services = $response->json('data.services');
        $serviceNames = collect($services)->pluck('service')->toArray();

        $this->assertContains('compute_server', $serviceNames);
        $this->assertContains('mysql_server', $serviceNames);
    }

    public function test_health_check_endpoint_handles_database_failure()
    {
        // Mock the MySQLHealthChecker to return DOWN status
        $this->app->bind(MySQLHealthChecker::class, function () {
            $mock = Mockery::mock(MySQLHealthChecker::class);
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
            ])
            ->assertJsonFragment([
                'service' => 'mysql_server',
                'status' => 'down'
            ]);
    }

    public function test_health_check_endpoint_handles_service_exception()
    {
        // Mock HealthCheckService to throw an exception
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

    public function test_health_check_endpoint_uses_correct_http_method()
    {
        // Test that POST method is not allowed
        $response = $this->postJson('/api/v1/health-check');
        $response->assertStatus(405);

        // Test that PUT method is not allowed
        $response = $this->putJson('/api/v1/health-check');
        $response->assertStatus(405);

        // Test that DELETE method is not allowed
        $response = $this->deleteJson('/api/v1/health-check');
        $response->assertStatus(405);
    }

    public function test_health_check_endpoint_returns_json_content_type()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');
    }

    public function test_health_check_endpoint_handles_mixed_service_statuses()
    {
        // Mock mixed service statuses
        $this->app->bind(HealthCheckService::class, function () {
            $mock = Mockery::mock(HealthCheckService::class);
            $mock->shouldReceive('getHealthStatus')
                ->andReturn(collect([
                    'compute_server' => ServerStatus::UP,
                    'mysql_server' => ServerStatus::DOWN,
                ]));
            return $mock;
        });

        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'unhealthy'
                ]
            ])
            ->assertJsonFragment([
                'service' => 'compute_server',
                'status' => 'up'
            ])
            ->assertJsonFragment([
                'service' => 'mysql_server',
                'status' => 'down'
            ]);
    }

    public function test_health_check_endpoint_performance()
    {
        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/health-check');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Health check should complete within 2 seconds
        $this->assertLessThan(2, $responseTime, 'Health check should be fast');
    }

    public function test_health_check_endpoint_concurrent_requests()
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

    public function test_health_check_endpoint_does_not_require_authentication()
    {
        // Health check should work without authentication
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Should not require any authentication headers
        $response->assertJson([
            'success' => true
        ]);
    }

    public function test_health_check_endpoint_response_format_consistency()
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
}
