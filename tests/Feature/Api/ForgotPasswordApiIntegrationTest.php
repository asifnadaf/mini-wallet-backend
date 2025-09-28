<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ForgotPasswordApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_api_routes_are_properly_registered()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        $routeUris = $apiRoutes->pluck('uri')->toArray();
        $this->assertContains('api/v1/forgot-password/email/token', $routeUris);
        $this->assertContains('api/v1/forgot-password/verify/token', $routeUris);
        $this->assertContains('api/v1/forgot-password/reset-password', $routeUris);
    }

    public function test_forgot_password_api_middleware_integration()
    {
        // Test guest middleware on forgot password endpoints
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(500); // User not found, but endpoint is accessible

        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => '123456'
        ]);
        $response->assertStatus(400); // Invalid token, but endpoint is accessible

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(400); // Invalid token, but endpoint is accessible
    }

    public function test_forgot_password_api_response_format_consistency()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);

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

    public function test_forgot_password_api_error_handling_integration()
    {
        // Test validation errors
        $response = $this->postJson('/api/v1/forgot-password/email/token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        $response = $this->postJson('/api/v1/forgot-password/verify/token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        $response = $this->postJson('/api/v1/forgot-password/reset-password', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);

        // Test invalid token
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => 'invalid-token'
        ]);
        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message'
            ]);
    }

    public function test_forgot_password_api_service_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(200);

        // Verify all services are working together
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_forgot_password_api_database_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send token
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(200);

        // Verify database operations
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);

        // Get token and verify
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        $verifyResponse = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200);

        // Reset password
        $resetResponse = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token,
            'password' => 'newpassword123'
        ]);

        $resetResponse->assertStatus(200);

        // Verify password was updated
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        // Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_forgot_password_api_performance_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Forgot password should complete within reasonable time
        $this->assertLessThan(3.0, $executionTime);
    }

    public function test_forgot_password_api_security_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test with non-existent user
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'nonexistent@example.com'
        ]);
        $response->assertStatus(500);

        // Test with invalid email format
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'invalid-email'
        ]);
        $response->assertStatus(422);

        // Test with SQL injection attempt
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => "john@example.com'; DROP TABLE users; --"
        ]);
        $response->assertStatus(422); // Should fail validation

        // Verify users table still exists
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_forgot_password_api_cors_integration()
    {
        $response = $this->withHeaders([
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/api/v1/forgot-password/email/token');

        // CORS headers should be present
        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_forgot_password_api_content_type_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');

        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => '123456'
        ]);
        $response->assertHeader('content-type', 'application/json');

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_forgot_password_api_versioning_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test that forgot password endpoints are properly versioned
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(200); // Should reach the endpoint

        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => '123456'
        ]);
        $response->assertStatus(400); // Should reach the endpoint (invalid token)

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(400); // Should reach the endpoint (invalid token)

        // Test that old version doesn't exist
        $response = $this->postJson('/api/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(404); // Should not exist

        $response = $this->postJson('/api/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => '123456'
        ]);
        $response->assertStatus(404); // Should not exist

        $response = $this->postJson('/api/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(404); // Should not exist
    }

    public function test_forgot_password_api_throttle_integration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test rate limiting for send token (no throttle in guest routes)
        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            $responses[] = $this->postJson('/api/v1/forgot-password/email/token', [
                'email' => 'john@example.com'
            ]);
        }

        // All requests should succeed or fail with validation (no rate limiting in tests)
        for ($i = 0; $i < 6; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 404, 422]);
        }

        // Test rate limiting for verify token (no throttle in guest routes)
        $responses = [];
        for ($i = 0; $i < 11; $i++) {
            $responses[] = $this->postJson('/api/v1/forgot-password/verify/token', [
                'email' => 'john@example.com',
                'token' => '123456'
            ]);
        }

        // All requests should succeed or fail with validation (no rate limiting in tests)
        for ($i = 0; $i < 11; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 400, 422]);
        }

        // Test rate limiting for reset password (no throttle in guest routes)
        $responses = [];
        for ($i = 0; $i < 11; $i++) {
            $responses[] = $this->postJson('/api/v1/forgot-password/reset-password', [
                'email' => 'john@example.com',
                'token' => '123456',
                'password' => 'newpassword123'
            ]);
        }

        // All requests should succeed or fail with validation (no rate limiting in tests)
        for ($i = 0; $i < 11; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 400, 422]);
        }
    }
}
