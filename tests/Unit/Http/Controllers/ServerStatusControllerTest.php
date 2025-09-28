<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\ServerStatusController;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\HealthCheckResponseFormatter;
use App\Contracts\ApiResponseInterface;
use App\Enums\ServerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;
use Exception;

class ServerStatusControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_health_check_returns_successful_response()
    {
        $mockHealthCheckService = Mockery::mock(HealthCheckService::class);
        $mockHealthFormatter = Mockery::mock(HealthCheckResponseFormatter::class);
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);

        $healthStatus = collect([
            'compute_server' => ServerStatus::UP,
            'mysql_server' => ServerStatus::UP,
        ]);

        $expectedResponse = response()->json([
            'success' => true,
            'message' => 'All services are operational',
            'data' => [
                'status' => 'healthy',
                'services' => []
            ]
        ]);

        $mockHealthCheckService->shouldReceive('getHealthStatus')
            ->once()
            ->andReturn($healthStatus);

        $mockHealthFormatter->shouldReceive('format')
            ->with($healthStatus)
            ->once()
            ->andReturn($expectedResponse);

        $controller = new ServerStatusController(
            $mockHealthCheckService,
            $mockHealthFormatter,
            $mockApiResponse
        );

        $result = $controller->healthCheck();

        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    public function test_health_check_handles_exception()
    {
        $mockHealthCheckService = Mockery::mock(HealthCheckService::class);
        $mockHealthFormatter = Mockery::mock(HealthCheckResponseFormatter::class);
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);

        $exception = new Exception('Service unavailable');
        $expectedErrorResponse = response()->json([
            'success' => false,
            'message' => 'Unable to complete health check',
            'errors' => 'Service unavailable'
        ], 503);

        $mockHealthCheckService->shouldReceive('getHealthStatus')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->with('Health check failed: Service unavailable')
            ->once();

        $mockApiResponse->shouldReceive('error')
            ->with('Unable to complete health check', 'Service unavailable', 503)
            ->once()
            ->andReturn($expectedErrorResponse);

        $controller = new ServerStatusController(
            $mockHealthCheckService,
            $mockHealthFormatter,
            $mockApiResponse
        );

        $result = $controller->healthCheck();

        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    public function test_health_check_logs_error_on_exception()
    {
        $mockHealthCheckService = Mockery::mock(HealthCheckService::class);
        $mockHealthFormatter = Mockery::mock(HealthCheckResponseFormatter::class);
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);

        $exception = new Exception('Database connection failed');

        $mockHealthCheckService->shouldReceive('getHealthStatus')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->with('Health check failed: Database connection failed')
            ->once();

        $mockApiResponse->shouldReceive('error')
            ->with('Unable to complete health check', 'Database connection failed', 503)
            ->once()
            ->andReturn(response()->json(['error' => true], 503));

        $controller = new ServerStatusController(
            $mockHealthCheckService,
            $mockHealthFormatter,
            $mockApiResponse
        );

        $result = $controller->healthCheck();

        // Verify the method was called and returned a response
        $this->assertInstanceOf(JsonResponse::class, $result);
    }
}
