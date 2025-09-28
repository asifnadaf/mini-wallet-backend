<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheckResponseFormatter;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function test_health_check_endpoint_returns_healthy_status_when_all_services_up()
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

        $response->assertStatus(200)
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

        // Verify specific services are present
        $services = $response->json('data.services');
        $serviceNames = collect($services)->pluck('service')->toArray();

        $this->assertContains('compute_server', $serviceNames);
        $this->assertContains('mysql_server', $serviceNames);
    }

    public function test_health_check_endpoint_handles_database_failure()
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
                'data' => [
                    'status' => 'unhealthy'
                ]
            ])
            ->assertJsonFragment([
                'service' => 'mysql_server',
                'status' => 'down'
            ]);
    }

    public function test_health_check_endpoint_returns_correct_response_format()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'All services are operational'
            ]);

        $responseData = $response->json('data');
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('services', $responseData);
        $this->assertIsArray($responseData['services']);
    }

    public function test_health_check_endpoint_handles_service_exception()
    {
        // Mock a service to throw an exception
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

    public function test_health_check_endpoint_handles_multiple_service_failures()
    {
        // Mock both services to fail
        $this->app->bind(HealthCheckService::class, function () {
            $mock = Mockery::mock(HealthCheckService::class);
            $mock->shouldReceive('getHealthStatus')
                ->andReturn(collect([
                    'compute_server' => ServerStatus::DOWN,
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
                'status' => 'down'
            ])
            ->assertJsonFragment([
                'service' => 'mysql_server',
                'status' => 'down'
            ]);
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
}
