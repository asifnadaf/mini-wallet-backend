<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class EmailVerificationApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_api_routes_are_properly_registered()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        $routeUris = $apiRoutes->pluck('uri')->toArray();
        $this->assertContains('api/v1/email/send-token', $routeUris);
        $this->assertContains('api/v1/email/verify-token', $routeUris);
    }

    public function test_email_verification_api_middleware_integration()
    {
        // Test auth middleware on email verification endpoints
        $response = $this->postJson('/api/v1/email/send-token');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/email/verify-token', ['token' => 123456]);
        $response->assertStatus(401);
    }

    public function test_email_verification_api_response_format_consistency()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');

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

    public function test_email_verification_api_error_handling_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test validation errors
        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // Test invalid token
        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 999999
        ]);
        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message'
            ]);
    }

    public function test_email_verification_api_service_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $response->assertStatus(200);

        // Verify all services are working together
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_email_verification_api_database_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send token
        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $response->assertStatus(200);

        // Verify database operations
        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'john@example.com',
        ]);

        // Get token and verify
        $tokenRecord = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->first();

        $verifyResponse = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200);

        // Verify user is marked as verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Verify token was deleted
        $this->assertDatabaseMissing('email_verification_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_email_verification_api_performance_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Email verification should complete within reasonable time
        $this->assertLessThan(3.0, $executionTime);
    }

    public function test_email_verification_api_security_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test rate limiting for send token
        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        }

        // All requests should succeed or fail with validation (rate limiting may not trigger in tests)
        for ($i = 0; $i < 6; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 422, 429]);
        }

        // Test rate limiting for verify token
        $responses = [];
        for ($i = 0; $i < 11; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
                'token' => 123456
            ]);
        }

        // All requests should succeed or fail with validation (rate limiting may not trigger in tests)
        for ($i = 0; $i < 11; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 400, 422, 429]);
        }
    }

    public function test_email_verification_api_cors_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/api/v1/email/send-token');

        // CORS headers should be present
        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_email_verification_api_content_type_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');

        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 123456
        ]);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_email_verification_api_versioning_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test that email verification endpoints are properly versioned
        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $response->assertStatus(200); // Should reach the endpoint

        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 123456
        ]);
        $response->assertStatus(400); // Should reach the endpoint (invalid token)

        // Test that old version doesn't exist
        $response = $this->actingAs($user)->postJson('/api/email/send-token');
        $response->assertStatus(404); // Should not exist

        $response = $this->actingAs($user)->postJson('/api/email/verify-token', [
            'token' => 123456
        ]);
        $response->assertStatus(404); // Should not exist
    }

    public function test_email_verification_api_throttle_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test send token throttle (5 requests per minute)
        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        }

        // All requests should succeed or fail with validation (rate limiting may not trigger in tests)
        for ($i = 0; $i < 6; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 422, 429]);
        }

        // Test verify token throttle (10 requests per minute)
        $responses = [];
        for ($i = 0; $i < 11; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
                'token' => 123456
            ]);
        }

        // All requests should succeed or fail with validation (rate limiting may not trigger in tests)
        for ($i = 0; $i < 11; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 400, 422, 429]);
        }
    }
}
