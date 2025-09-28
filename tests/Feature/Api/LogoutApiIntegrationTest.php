<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_api_routes_are_properly_registered()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        $routeUris = $apiRoutes->pluck('uri')->toArray();
        $this->assertContains('api/v1/logout', $routeUris);
    }

    public function test_logout_api_middleware_integration()
    {
        // Test auth middleware on logout
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);

        // Test with valid authentication
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);
    }

    public function test_logout_api_response_format_consistency()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

        // Verify response format consistency
        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertNull($responseData['data']);
    }

    public function test_logout_api_error_handling_integration()
    {
        // Test without authentication
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);

        // Test with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);

        // Test with malformed authorization header
        $response = $this->withHeaders([
            'Authorization' => 'InvalidFormat token123',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_logout_api_service_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify all services are working together
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_api_database_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Verify token exists before logout
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_api_performance_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Logout should complete within reasonable time
        $this->assertLessThan(2.0, $executionTime);
    }

    public function test_logout_api_security_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Test rate limiting
        $responses = [];
        for ($i = 0; $i < 12; $i++) {
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/v1/logout');
        }

        // First 10 requests should succeed or fail with 401 (token already deleted)
        for ($i = 0; $i < 10; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 401]);
        }

        // 11th and 12th requests should be rate limited
        $this->assertEquals(429, $responses[10]->getStatusCode());
        $this->assertEquals(429, $responses[11]->getStatusCode());
    }

    public function test_logout_api_cors_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/api/v1/logout');

        // CORS headers should be present
        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_logout_api_content_type_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');
    }

    public function test_logout_api_versioning_integration()
    {
        // Test that logout endpoint is properly versioned
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200); // Should reach the endpoint

        // Test that old version doesn't exist
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(404); // Should not exist
    }

    public function test_logout_api_throttle_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Make requests up to the throttle limit
        $responses = [];
        for ($i = 0; $i < 11; $i++) {
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/v1/logout');
        }

        // First 10 requests should succeed or fail with 401 (token already deleted)
        for ($i = 0; $i < 10; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 401]);
        }

        // 11th request should be rate limited
        $this->assertEquals(429, $responses[10]->getStatusCode());
    }
}
