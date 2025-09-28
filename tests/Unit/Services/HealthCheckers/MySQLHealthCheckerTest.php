<?php

namespace Tests\Unit\Services\HealthCheckers;

use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;
use Exception;

class MySQLHealthCheckerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_check_returns_up_when_database_is_accessible()
    {
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->once()
            ->andReturn([['1' => 1]]);

        $checker = new MySQLHealthChecker();
        $result = $checker->check();

        $this->assertEquals(ServerStatus::UP, $result);
    }

    public function test_check_returns_down_when_database_throws_exception()
    {
        $exception = new Exception('Connection failed');

        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->with('MySQL health check failed: Connection failed')
            ->once();

        $checker = new MySQLHealthChecker();
        $result = $checker->check();

        $this->assertEquals(ServerStatus::DOWN, $result);
    }

    public function test_get_name_returns_correct_name()
    {
        $checker = new MySQLHealthChecker();
        $name = $checker->getName();

        $this->assertEquals('mysql_server', $name);
    }

    public function test_implements_health_checkable_interface()
    {
        $checker = new MySQLHealthChecker();

        $this->assertInstanceOf('App\Contracts\HealthCheckable', $checker);
    }

    public function test_check_logs_error_when_database_fails()
    {
        $exception = new Exception('Database connection timeout');

        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->with('MySQL health check failed: Database connection timeout')
            ->once();

        $checker = new MySQLHealthChecker();
        $result = $checker->check();

        // Verify the method was called and returned a status
        $this->assertInstanceOf(ServerStatus::class, $result);
    }
}
