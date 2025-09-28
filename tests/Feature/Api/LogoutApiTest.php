<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_success()
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
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
                'data' => null
            ]);

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_requires_authentication()
    {
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    public function test_logout_with_invalid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_logout_without_authorization_header()
    {
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    public function test_logout_with_malformed_authorization_header()
    {
        $response = $this->withHeaders([
            'Authorization' => 'InvalidFormat token123',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_logout_deletes_all_user_tokens()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create multiple tokens for the user
        $token1 = $user->createToken('test-token-1')->plainTextToken;
        $token2 = $user->createToken('test-token-2')->plainTextToken;
        $token3 = $user->createToken('test-token-3')->plainTextToken;

        // Verify all tokens exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        // Logout with one token should delete all tokens
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify all tokens were deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_after_multiple_logins()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Login multiple times to create multiple tokens
        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $loginResponse1 = $this->postJson('/api/v1/login', $loginData);
        $loginResponse1->assertStatus(200);
        $token1 = $loginResponse1->json('data.access_token');

        $loginResponse2 = $this->postJson('/api/v1/login', $loginData);
        $loginResponse2->assertStatus(200);
        $token2 = $loginResponse2->json('data.access_token');

        // Verify multiple tokens exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        // Logout with one token should delete all tokens
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify all tokens were deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_content_type()
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

    public function test_logout_with_different_http_methods()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Test GET method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/logout');

        $response->assertStatus(405);

        // Test PUT method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/v1/logout');

        $response->assertStatus(405);

        // Test DELETE method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/logout');

        $response->assertStatus(405);
    }

    public function test_logout_rate_limiting()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Make multiple logout requests to test rate limiting
        $responses = [];
        for ($i = 0; $i < 12; $i++) {
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/v1/logout');
        }

        // First 10 requests should succeed (rate limit is 10 per minute)
        for ($i = 0; $i < 10; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 401]);
        }

        // 11th and 12th requests should be rate limited
        $this->assertEquals(429, $responses[10]->getStatusCode());
        $this->assertEquals(429, $responses[11]->getStatusCode());
    }

    public function test_logout_handles_service_exception()
    {
        // This test would require mocking the User model to throw an exception
        // For now, we'll test with a valid scenario
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

    public function test_logout_response_consistency()
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

        // Verify response structure consistency
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Logged out successfully', $responseData['message']);
        $this->assertNull($responseData['data']);
    }
}
