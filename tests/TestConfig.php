<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * Test configuration and utilities for health check tests
 */
class TestConfig
{
    /**
     * Setup test environment
     */
    public static function setupTestEnvironment(): void
    {
        // Ensure database is properly configured for testing
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        // Disable logging during tests unless specifically needed
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
    }

    /**
     * Clean up after tests
     */
    public static function cleanup(): void
    {
        Mockery::close();
    }

    /**
     * Mock database failure for testing
     */
    public static function mockDatabaseFailure(string $errorMessage = 'Database connection failed'): void
    {
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->andThrow(new \Exception($errorMessage));

        Log::shouldReceive('error')
            ->with("MySQL health check failed: {$errorMessage}")
            ->once();
    }

    /**
     * Mock successful database connection
     */
    public static function mockDatabaseSuccess(): void
    {
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->andReturn([['1' => 1]]);
    }

    /**
     * Get expected healthy response structure
     */
    public static function getHealthyResponseStructure(): array
    {
        return [
            'success' => true,
            'message' => 'All services are operational',
            'data' => [
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
            ]
        ];
    }

    /**
     * Get expected unhealthy response structure
     */
    public static function getUnhealthyResponseStructure(): array
    {
        return [
            'success' => true,
            'message' => 'Some services are experiencing issues',
            'data' => [
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
            ]
        ];
    }

    /**
     * Get expected error response structure
     */
    public static function getErrorResponseStructure(string $errorMessage = 'Service unavailable'): array
    {
        return [
            'success' => false,
            'message' => 'Unable to complete health check',
            'errors' => $errorMessage
        ];
    }
}
