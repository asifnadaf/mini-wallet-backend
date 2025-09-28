<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_endpoint_works()
    {
        $response = $this->getJson('/api/v1/health-check');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    public function test_user_registration_with_valid_data()
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

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_user_registration_with_invalid_data()
    {
        $invalidData = [
            'name' => '', // Empty name
            'email' => 'invalid-email', // Invalid email
            'password' => '123', // Weak password
        ];

        $response = $this->postJson('/api/v1/register', $invalidData);

        $response->assertStatus(422);
    }

    public function test_user_registration_duplicate_email()
    {
        // Create existing user
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422);
    }

    public function test_api_endpoints_use_correct_http_methods()
    {
        // Health check should only accept GET
        $response = $this->postJson('/api/v1/health-check');
        $response->assertStatus(405);

        $response = $this->putJson('/api/v1/health-check');
        $response->assertStatus(405);

        $response = $this->deleteJson('/api/v1/health-check');
        $response->assertStatus(405);

        // Registration should only accept POST
        $response = $this->getJson('/api/v1/register');
        $response->assertStatus(405);

        $response = $this->putJson('/api/v1/register');
        $response->assertStatus(405);

        $response = $this->deleteJson('/api/v1/register');
        $response->assertStatus(405);
    }

    public function test_api_returns_json_content_type()
    {
        $response = $this->getJson('/api/v1/health-check');
        $response->assertHeader('content-type', 'application/json');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_api_handles_invalid_routes()
    {
        $response = $this->getJson('/api/v1/invalid');
        $response->assertStatus(404);

        $response = $this->postJson('/api/v1/invalid');
        $response->assertStatus(404);
    }

    public function test_api_versioning()
    {
        // v1 should work
        $response = $this->getJson('/api/v1/health-check');
        $response->assertStatus(200);

        // v2 should not work
        $response = $this->getJson('/api/v2/health-check');
        $response->assertStatus(404);
    }

    public function test_api_performance()
    {
        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/health-check');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(2, $executionTime, 'API should respond quickly');
    }

    public function test_api_creates_access_token()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(201);

        $responseData = $response->json('data');
        $this->assertArrayHasKey('access_token', $responseData);
        $this->assertNotEmpty($responseData['access_token']);

        // Verify token exists in database
        $user = User::where('email', 'john@example.com')->first();
        $this->assertCount(1, $user->tokens);
    }

    public function test_api_guest_middleware()
    {
        // Create and authenticate a user
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
}
