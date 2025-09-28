<?php

namespace Tests\Unit\Services;

use App\Services\HealthCheckResponseFormatter;
use App\Services\ApiResponseService;
use App\Enums\ServerStatus;
use App\Contracts\ApiResponseInterface;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Mockery;

class HealthCheckResponseFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_format_returns_success_response_for_healthy_services()
    {
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $formatter = new HealthCheckResponseFormatter($mockApiResponse);

        $healthStatus = collect([
            'compute_server' => ServerStatus::UP,
            'mysql_server' => ServerStatus::UP,
        ]);

        $expectedData = [
            'status' => 'healthy',
            'services' => [
                [
                    'service' => 'compute_server',
                    'status' => 'up',
                    'last_checked' => now()->format('Y-m-d H:i:s')
                ],
                [
                    'service' => 'mysql_server',
                    'status' => 'up',
                    'last_checked' => now()->format('Y-m-d H:i:s')
                ]
            ]
        ];

        $mockApiResponse->shouldReceive('success')
            ->with($expectedData, 'All services are operational')
            ->once()
            ->andReturn(response()->json(['success' => true]));

        $result = $formatter->format($healthStatus);

        // Verify the method was called and returned a response
        $this->assertNotNull($result);
    }

    public function test_format_returns_unhealthy_status_for_down_services()
    {
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $formatter = new HealthCheckResponseFormatter($mockApiResponse);

        $healthStatus = collect([
            'compute_server' => ServerStatus::UP,
            'mysql_server' => ServerStatus::DOWN,
        ]);

        $expectedData = [
            'status' => 'unhealthy',
            'services' => [
                [
                    'service' => 'compute_server',
                    'status' => 'up',
                    'last_checked' => now()->format('Y-m-d H:i:s')
                ],
                [
                    'service' => 'mysql_server',
                    'status' => 'down',
                    'last_checked' => now()->format('Y-m-d H:i:s')
                ]
            ]
        ];

        $mockApiResponse->shouldReceive('success')
            ->with($expectedData, 'Some services are experiencing issues')
            ->once()
            ->andReturn(response()->json(['success' => true]));

        $result = $formatter->format($healthStatus);

        // Verify the method was called and returned a response
        $this->assertNotNull($result);
    }

    public function test_format_handles_unknown_status()
    {
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $formatter = new HealthCheckResponseFormatter($mockApiResponse);

        $healthStatus = collect([
            'compute_server' => ServerStatus::UNKNOWN,
        ]);

        $expectedData = [
            'status' => 'unhealthy',
            'services' => [
                [
                    'service' => 'compute_server',
                    'status' => 'unknown',
                    'last_checked' => now()->format('Y-m-d H:i:s')
                ]
            ]
        ];

        $mockApiResponse->shouldReceive('success')
            ->with($expectedData, 'Some services are experiencing issues')
            ->once()
            ->andReturn(response()->json(['success' => true]));

        $result = $formatter->format($healthStatus);

        // Verify the method was called and returned a response
        $this->assertNotNull($result);
    }

    public function test_format_handles_empty_collection()
    {
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $formatter = new HealthCheckResponseFormatter($mockApiResponse);

        $healthStatus = collect([]);

        $expectedData = [
            'status' => 'healthy',
            'services' => []
        ];

        $mockApiResponse->shouldReceive('success')
            ->with($expectedData, 'All services are operational')
            ->once()
            ->andReturn(response()->json(['success' => true]));

        $result = $formatter->format($healthStatus);

        // Verify the method was called and returned a response
        $this->assertNotNull($result);
    }
}
