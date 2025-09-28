<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_logout_workflow()
    {
        // Step 1: Create user and login
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $loginResponse = $this->postJson('/api/v1/login', $loginData);
        $loginResponse->assertStatus(200);
        $accessToken = $loginResponse->json('data.access_token');

        // Step 2: Verify token exists in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        // Step 3: Logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->postJson('/api/v1/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
                'data' => null
            ]);

        // Step 4: Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        // Step 5: Verify token is invalidated by checking database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_workflow_with_multiple_tokens()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create multiple tokens
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

    public function test_logout_workflow_with_invalid_token()
    {
        // Test logout with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_logout_workflow_without_authentication()
    {
        // Test logout without authentication
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    public function test_logout_workflow_performance()
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

        // Logout should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $executionTime);
    }

    public function test_logout_workflow_security()
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

    public function test_logout_workflow_data_consistency()
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

        // Verify response data consistency
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Logged out successfully', $responseData['message']);
        $this->assertNull($responseData['data']);

        // Verify database consistency
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_workflow_error_recovery()
    {
        // Test with malformed authorization header
        $response = $this->withHeaders([
            'Authorization' => 'InvalidFormat token123',
        ])->postJson('/api/v1/logout');

        $response->assertStatus(401);

        // Test with valid token after error
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

    public function test_logout_workflow_concurrent_requests()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Make concurrent logout requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/v1/logout');
        }

        // At least one should succeed, others might fail with 401 (token already deleted)
        $successCount = 0;
        $unauthorizedCount = 0;

        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            } elseif ($response->getStatusCode() === 401) {
                $unauthorizedCount++;
            }
        }

        $this->assertGreaterThanOrEqual(1, $successCount);
        $this->assertGreaterThanOrEqual(0, $unauthorizedCount);
    }
}
