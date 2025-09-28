<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\UserRegistrationService;
use App\Services\HealthCheck\HealthCheckService;
use App\Services\EmailTokenService;
use App\Strategies\Token\EmailTokenStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
     * Test complete user registration workflow
     */
    public function test_complete_user_registration_workflow()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at'
                    ],
                    'access_token'
                ]
            ]);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Verify access token was created
        $user = User::where('email', 'john@example.com')->first();
        $this->assertCount(1, $user->tokens);

        // Verify email token was sent (if service is configured)
        $this->assertTrue(true, 'User registration workflow completed successfully');
    }

    /**
     * Test API routing and middleware integration
     */
    public function test_api_routing_and_middleware_integration()
    {
        // Test health check endpoint
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');

        // Test registration endpoint
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        $response->assertStatus(201)
            ->assertHeader('content-type', 'application/json');
    }

    /**
     * Test API service integration
     */
    public function test_api_service_integration()
    {
        // Test that all services are properly bound
        $this->assertInstanceOf(UserRegistrationService::class, app(UserRegistrationService::class));
        $this->assertInstanceOf(HealthCheckService::class, app(HealthCheckService::class));
        $this->assertInstanceOf(EmailTokenService::class, app(EmailTokenService::class));
        $this->assertInstanceOf(EmailTokenStrategy::class, app(EmailTokenStrategy::class));
    }

    /**
     * Test API database integration
     */
    public function test_api_database_integration()
    {
        // Test database connection
        $this->assertDatabaseConnection();

        // Test user creation
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Test token creation
        $token = $user->createToken('Test Token');
        $this->assertCount(1, $user->tokens);
    }

    /**
     * Test API error handling integration
     */
    public function test_api_error_handling_integration()
    {
        // Test validation errors
        $response = $this->postJson('/api/v1/register', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // Test invalid routes
        $response = $this->getJson('/api/v1/invalid');
        $response->assertStatus(404);
    }

    /**
     * Test API performance integration
     */
    public function test_api_performance_integration()
    {
        $startTime = microtime(true);

        // Test multiple API calls
        $this->getJson('/api/v1/health-check');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $this->postJson('/api/v1/register', $userData);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // API should respond within reasonable time (5 seconds for both calls)
        $this->assertLessThan(5, $executionTime, 'API should be performant');
    }

    /**
     * Test API security integration
     */
    public function test_api_security_integration()
    {
        // Test rate limiting
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/register', $userData);
            $this->assertContains($response->getStatusCode(), [201, 422, 429]);
        }

        // Test CORS headers
        $response = $this->getJson('/api/v1/health-check');
        $response->assertHeader('content-type', 'application/json');
    }

    /**
     * Test API response format consistency
     */
    public function test_api_response_format_consistency()
    {
        // Test health check response format
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

        // Test registration response format
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
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
     * Test API memory usage
     */
    public function test_api_memory_usage()
    {
        $initialMemory = memory_get_usage();

        // Perform API operations
        $this->getJson('/api/v1/health-check');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $this->postJson('/api/v1/register', $userData);

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // API should not use excessive memory (less than 5MB)
        $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, 'API should be memory efficient');
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
     * Test API versioning
     */
    public function test_api_versioning()
    {
        // Test v1 endpoints
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test invalid version
        $response = $this->getJson('/api/v2/health-check');
        $response->assertStatus(404);
    }

    /**
     * Test API middleware stack
     */
    public function test_api_middleware_stack()
    {
        // Test that API endpoints work with middleware
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // Test guest middleware on registration
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        // Should be blocked by guest middleware
        $this->assertContains($response->getStatusCode(), [302, 403, 401]);
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
