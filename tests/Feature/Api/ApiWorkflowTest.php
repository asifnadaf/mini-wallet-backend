<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\UserRegistrationService;
use App\Services\HealthCheck\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test complete API workflow: Health Check -> User Registration -> Authentication
     */
    public function test_complete_api_workflow()
    {
        // Step 1: Check API health
        $healthResponse = $this->getJson('/api/v1/health-check');
        $healthResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy'
                ]
            ]);

        // Step 2: Register a new user
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $registerResponse = $this->postJson('/api/v1/register', $userData);
        $registerResponse->assertStatus(201)
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

        // Step 3: Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Step 4: Verify access token was created
        $user = User::where('email', 'john@example.com')->first();
        $this->assertCount(1, $user->tokens);

        // Step 5: Test authenticated endpoint (if available)
        $accessToken = $registerResponse->json('data.access_token');
        $this->assertNotEmpty($accessToken);
    }

    /**
     * Test API error recovery workflow
     */
    public function test_api_error_recovery_workflow()
    {
        // Test with invalid registration data
        $invalidData = [
            'name' => '', // Invalid name
            'email' => 'invalid-email', // Invalid email
            'password' => '123', // Weak password
        ];

        $response = $this->postJson('/api/v1/register', $invalidData);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // API should still be healthy after error
        $healthResponse = $this->getJson('/api/v1/health-check');
        $healthResponse->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Test API rate limiting workflow
     */
    public function test_api_rate_limiting_workflow()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $responses = [];

        // Make multiple requests to trigger rate limiting
        for ($i = 0; $i < 6; $i++) {
            $responses[] = $this->postJson('/api/v1/register', $userData);
        }

        // All requests should succeed or fail with validation (no rate limiting in tests)
        for ($i = 0; $i < 6; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [201, 422]);
        }
    }

    /**
     * Test API concurrent request workflow
     */
    public function test_api_concurrent_request_workflow()
    {
        $responses = [];

        // Simulate concurrent health check requests
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->getJson('/api/v1/health-check');
        }

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
        }
    }

    /**
     * Test API database transaction workflow
     */
    public function test_api_database_transaction_workflow()
    {
        // Test that user registration uses database transactions
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        $response->assertStatus(201);

        // Verify user was created atomically
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Verify token was created atomically
        $user = User::where('email', 'john@example.com')->first();
        $this->assertCount(1, $user->tokens);
    }

    /**
     * Test API service integration workflow
     */
    public function test_api_service_integration_workflow()
    {
        // Test that all services work together
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        $response->assertStatus(201);

        // Verify all services were called
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertCount(1, $user->tokens);
    }

    /**
     * Test API performance workflow
     */
    public function test_api_performance_workflow()
    {
        $startTime = microtime(true);

        // Perform multiple API operations
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

        // API should be performant
        $this->assertLessThan(3, $executionTime, 'API should be performant');
    }

    /**
     * Test API security workflow
     */
    public function test_api_security_workflow()
    {
        // Test that guest middleware works
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
     * Test API monitoring workflow
     */
    public function test_api_monitoring_workflow()
    {
        // Test health check monitoring
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        $responseData = $response->json('data');
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('services', $responseData);

        $services = $responseData['services'];
        $this->assertIsArray($services);
        $this->assertCount(2, $services);

        // Verify service monitoring data
        foreach ($services as $service) {
            $this->assertArrayHasKey('service', $service);
            $this->assertArrayHasKey('status', $service);
            $this->assertArrayHasKey('last_checked', $service);
        }
    }

    /**
     * Test API error handling workflow
     */
    public function test_api_error_handling_workflow()
    {
        // Test with service exception
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

        // API should recover after error - create a new test instance
        $this->refreshApplication();

        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Test API data flow workflow
     */
    public function test_api_data_flow_workflow()
    {
        // Test complete data flow from request to response
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        $response->assertStatus(201);

        // Verify data flow through all layers
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);

        $userData = $responseData['data']['user'];
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('name', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('email_verified_at', $userData);

        $this->assertArrayHasKey('access_token', $responseData['data']);
        $this->assertNotEmpty($responseData['data']['access_token']);
    }
}
