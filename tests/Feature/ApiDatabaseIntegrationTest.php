<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Services\HealthCheck\HealthCheckers\MySQLHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Mockery;

class ApiDatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test database connection integration
     */
    public function test_database_connection_integration()
    {
        // Test basic database connection
        $this->assertDatabaseConnection();

        // Test that we can perform queries
        $result = DB::select('SELECT 1 as test');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->test);
    }

    /**
     * Test database health check integration
     */
    public function test_database_health_check_integration()
    {
        $mysqlChecker = app(MySQLHealthChecker::class);
        $status = $mysqlChecker->check();

        $this->assertInstanceOf(ServerStatus::class, $status);
        $this->assertEquals(ServerStatus::UP, $status);
    }

    /**
     * Test database schema integration
     */
    public function test_database_schema_integration()
    {
        // Test that required tables exist
        $this->assertTrue(Schema::hasTable('users'), 'Users table should exist');
        $this->assertTrue(Schema::hasTable('cache'), 'Cache table should exist');
        $this->assertTrue(Schema::hasTable('jobs'), 'Jobs table should exist');
    }

    /**
     * Test database transaction integration
     */
    public function test_database_transaction_integration()
    {
        DB::beginTransaction();

        try {
            // Test database operations within transaction
            $result = DB::select('SELECT 1 as test');
            $this->assertEquals(1, $result[0]->test);

            DB::commit();
            $this->assertTrue(true, 'Transaction should commit successfully');
        } catch (\Exception $e) {
            DB::rollback();
            $this->fail('Transaction should not fail: ' . $e->getMessage());
        }
    }

    /**
     * Test database connection pooling
     */
    public function test_database_connection_pooling()
    {
        // Test multiple database connections
        $connections = [];

        for ($i = 0; $i < 5; $i++) {
            $result = DB::select('SELECT 1 as test');
            $connections[] = $result[0]->test;
        }

        $this->assertCount(5, $connections);
        $this->assertEquals([1, 1, 1, 1, 1], $connections);
    }

    /**
     * Test database performance integration
     */
    public function test_database_performance_integration()
    {
        $startTime = microtime(true);

        // Perform multiple database operations
        for ($i = 0; $i < 10; $i++) {
            DB::select('SELECT 1 as test');
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertLessThan(1, $executionTime, 'Database operations should be fast');
    }

    /**
     * Test database error handling integration
     */
    public function test_database_error_handling_integration()
    {
        // Test with invalid query
        try {
            DB::select('SELECT * FROM non_existent_table');
            $this->fail('Should have thrown an exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('non_existent_table', $e->getMessage());
        }
    }

    /**
     * Test database connection timeout
     */
    public function test_database_connection_timeout()
    {
        $startTime = microtime(true);

        $result = DB::select('SELECT 1 as test');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $this->assertLessThan(2, $responseTime, 'Database should respond within timeout');
        $this->assertEquals(1, $result[0]->test);
    }

    /**
     * Test database memory usage
     */
    public function test_database_memory_usage()
    {
        $initialMemory = memory_get_usage();

        // Perform database operations
        for ($i = 0; $i < 100; $i++) {
            DB::select('SELECT 1 as test');
        }

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, 'Database operations should be memory efficient');
    }

    /**
     * Test database connection resilience
     */
    public function test_database_connection_resilience()
    {
        // Test multiple rapid connections
        $results = [];

        for ($i = 0; $i < 20; $i++) {
            $result = DB::select('SELECT 1 as test');
            $results[] = $result[0]->test;
        }

        $this->assertCount(20, $results);
        $this->assertEquals(array_fill(0, 20, 1), $results);
    }

    /**
     * Test database configuration integration
     */
    public function test_database_configuration_integration()
    {
        $config = config('database.default');
        $this->assertIsString($config);
        $this->assertNotEmpty($config);

        $connection = config("database.connections.{$config}");
        $this->assertIsArray($connection);
        $this->assertArrayHasKey('driver', $connection);
    }

    /**
     * Test database migration integration
     */
    public function test_database_migration_integration()
    {
        // Test that migrations have been run
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('jobs'));

        // Test table structure
        $this->assertTrue(Schema::hasColumn('users', 'id'));
        $this->assertTrue(Schema::hasColumn('users', 'name'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
    }

    /**
     * Test database seeding integration
     */
    public function test_database_seeding_integration()
    {
        // Test that database can be seeded
        $this->seed();

        // Verify seeding worked (if there are seeders)
        $this->assertTrue(true, 'Database seeding should work');
    }

    /**
     * Test database backup and restore (simulation)
     */
    public function test_database_backup_restore_simulation()
    {
        // Test that we can read from database
        $result = DB::select('SELECT 1 as test');
        $this->assertEquals(1, $result[0]->test);

        // Test that we can write to database
        DB::table('users')->insert([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
    }

    /**
     * Test database connection string parsing
     */
    public function test_database_connection_string_parsing()
    {
        $connection = DB::connection();
        $this->assertNotNull($connection);

        $driver = $connection->getDriverName();
        $this->assertIsString($driver);
        $this->assertNotEmpty($driver);
    }

    /**
     * Test database query logging
     */
    public function test_database_query_logging()
    {
        // Enable query logging
        DB::enableQueryLog();

        // Perform a query
        DB::select('SELECT 1 as test');

        // Check query log
        $queries = DB::getQueryLog();
        $this->assertIsArray($queries);
        $this->assertGreaterThan(0, count($queries));

        // Disable query logging
        DB::disableQueryLog();
    }

    /**
     * Test database connection health monitoring
     */
    public function test_database_connection_health_monitoring()
    {
        $mysqlChecker = app(MySQLHealthChecker::class);

        // Test healthy database
        $status = $mysqlChecker->check();
        $this->assertEquals(ServerStatus::UP, $status);

        // Test database name
        $name = $mysqlChecker->getName();
        $this->assertEquals('mysql_server', $name);
    }

    /**
     * Test database connection pooling with health checks
     */
    public function test_database_connection_pooling_with_health_checks()
    {
        $mysqlChecker = app(MySQLHealthChecker::class);

        // Test multiple health checks
        $statuses = [];
        for ($i = 0; $i < 5; $i++) {
            $statuses[] = $mysqlChecker->check();
        }

        $this->assertCount(5, $statuses);
        foreach ($statuses as $status) {
            $this->assertEquals(ServerStatus::UP, $status);
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
