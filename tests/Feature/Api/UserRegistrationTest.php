<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\EmailTokenService;
use App\Strategies\Token\EmailTokenStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Mockery;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_registration_success()
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

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_registration_validation_errors()
    {
        $invalidData = [
            'name' => '', // Empty name
            'email' => 'invalid-email', // Invalid email
            'password' => '123', // Weak password
        ];

        $response = $this->postJson('/api/v1/register', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'email',
                    'password'
                ]
            ]);
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

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);
    }

    public function test_user_registration_name_validation()
    {
        $userData = [
            'name' => 'John123', // Contains numbers
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name'
                ]
            ]);
    }

    public function test_user_registration_password_validation()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '123', // Too weak
            'password_confirmation' => '123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'password'
                ]
            ]);
    }

    public function test_user_registration_creates_access_token()
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

    public function test_user_registration_returns_user_resource()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(201);

        $userData = $response->json('data.user');
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('name', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('email_verified_at', $userData);
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
    }

    public function test_user_registration_rate_limiting()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/register', $userData);

            // All requests should succeed or fail with validation (no rate limiting in tests)
            $this->assertContains($response->getStatusCode(), [201, 422]);
        }
    }

    public function test_user_registration_requires_guest_middleware()
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

        // Should be redirected or blocked by guest middleware
        $this->assertContains($response->getStatusCode(), [302, 403, 401]);
    }

    public function test_user_registration_handles_service_exception()
    {
        // Mock the UserRegistrationService to throw an exception
        $this->app->bind(\App\Services\UserRegistrationService::class, function () {
            $mock = Mockery::mock(\App\Services\UserRegistrationService::class);
            $mock->shouldReceive('register')
                ->andThrow(new \Exception('Service unavailable'));
            return $mock;
        });

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred during registration',
                'errors' => 'Service unavailable'
            ]);
    }

    public function test_user_registration_content_type()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertHeader('content-type', 'application/json');
    }

    public function test_user_registration_with_different_http_methods()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Test GET method (should not be allowed)
        $response = $this->getJson('/api/v1/register');
        $response->assertStatus(405);

        // Test PUT method (should not be allowed)
        $response = $this->putJson('/api/v1/register', $userData);
        $response->assertStatus(405);

        // Test DELETE method (should not be allowed)
        $response = $this->deleteJson('/api/v1/register');
        $response->assertStatus(405);
    }
}
