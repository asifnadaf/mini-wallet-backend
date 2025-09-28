<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_api_routes_are_properly_registered()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        $routeUris = $apiRoutes->pluck('uri')->toArray();
        $this->assertContains('api/v1/login', $routeUris);
        $this->assertContains('api/v1/logout', $routeUris);
    }

    public function test_login_api_middleware_integration()
    {
        // Test guest middleware on login
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        // Should be blocked by guest middleware when already authenticated
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/login', $loginData);

        // Guest middleware might redirect (302) or return 401 depending on configuration
        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_logout_api_middleware_integration()
    {
        // Test auth middleware on logout
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    public function test_login_api_response_format_consistency()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(200)
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

        // Verify response format consistency
        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);
        $this->assertIsArray($responseData['data']['user']);
        $this->assertIsString($responseData['data']['access_token']);
    }

    public function test_login_api_error_handling_integration()
    {
        // Test validation errors
        $response = $this->postJson('/api/v1/login', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // Test invalid credentials
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(401)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    public function test_login_api_service_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(200);

        // Verify all services are working together
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_api_database_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(200);

        // Verify database operations
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        // Test logout database integration
        $accessToken = $response->json('data.access_token');
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->postJson('/api/v1/logout');

        $logoutResponse->assertStatus(200);

        // Verify token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_api_performance_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/login', $loginData);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Login should complete within reasonable time
        $this->assertLessThan(2.0, $executionTime);
    }

    public function test_login_api_security_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test SQL injection protection
        $maliciousData = [
            'email' => "john@example.com'; DROP TABLE users; --",
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $maliciousData);
        $response->assertStatus(422); // Should fail validation

        // Verify users table still exists
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        // Test XSS protection
        $xssData = [
            'email' => '<script>alert("xss")</script>@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $xssData);
        $response->assertStatus(422); // Should fail validation
    }

    public function test_login_api_cors_integration()
    {
        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->options('/api/v1/login');

        // CORS headers should be present
        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_login_api_content_type_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');
    }

    public function test_login_api_versioning_integration()
    {
        // Test that login endpoint is properly versioned
        $response = $this->postJson('/api/v1/login', []);
        $response->assertStatus(422); // Should reach the endpoint (validation error)

        // Test that old version doesn't exist
        $response = $this->postJson('/api/login', []);
        $response->assertStatus(404); // Should not exist
    }
}
