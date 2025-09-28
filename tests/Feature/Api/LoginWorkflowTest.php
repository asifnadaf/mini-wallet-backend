<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_login_workflow()
    {
        // Create a user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Step 1: Login with valid credentials
        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $loginResponse = $this->postJson('/api/v1/login', $loginData);
        $loginResponse->assertStatus(200)
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

        $accessToken = $loginResponse->json('data.access_token');
        $this->assertNotEmpty($accessToken);

        // Step 2: Logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->postJson('/api/v1/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        // Step 3: Verify token is invalidated by checking database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_workflow_with_invalid_credentials()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Try to login with wrong password
        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ];

        $loginResponse = $this->postJson('/api/v1/login', $loginData);
        $loginResponse->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);

        // Try to login with non-existent email
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $loginResponse = $this->postJson('/api/v1/login', $loginData);
        $loginResponse->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
    }

    public function test_login_workflow_with_validation_errors()
    {
        // Test with missing email
        $loginData = [
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);

        // Test with invalid email format
        $loginData = [
            'email' => 'invalid-email',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);

        // Test with missing password
        $loginData = [
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'password'
                ]
            ]);
    }

    public function test_multiple_login_sessions()
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

        // Login first time
        $loginResponse1 = $this->postJson('/api/v1/login', $loginData);
        $loginResponse1->assertStatus(200);
        $token1 = $loginResponse1->json('data.access_token');

        // Login second time
        $loginResponse2 = $this->postJson('/api/v1/login', $loginData);
        $loginResponse2->assertStatus(200);
        $token2 = $loginResponse2->json('data.access_token');

        // Both tokens should be different
        $this->assertNotEquals($token1, $token2);

        // Both tokens should work
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/v1/logout');

        $response1->assertStatus(200);

        // Second token should still work
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->postJson('/api/v1/logout');

        $response2->assertStatus(200);
    }

    public function test_login_workflow_performance()
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

        // Login should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $executionTime);
    }

    public function test_login_workflow_security()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test rate limiting (if implemented)
        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ];

        // Make multiple failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/login', $loginData);
            $response->assertStatus(401);
        }

        // The 6th attempt might be rate limited (depending on configuration)
        $response = $this->postJson('/api/v1/login', $loginData);
        // This could be 401 (still invalid) or 429 (rate limited)
        $this->assertContains($response->getStatusCode(), [401, 429]);
    }

    public function test_login_workflow_data_consistency()
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

        $userData = $response->json('data.user');

        // Verify user data consistency
        $this->assertEquals($user->id, $userData['id']);
        $this->assertEquals($user->name, $userData['name']);
        $this->assertEquals($user->email, $userData['email']);
        $this->assertEquals($user->email_verified_at, $userData['email_verified_at']);

        // Verify token is created in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_workflow_error_recovery()
    {
        // Test with malformed JSON
        $response = $this->post('/api/v1/login', [], [
            'Content-Type' => 'application/json',
        ]);

        // Laravel might redirect for malformed requests
        $this->assertContains($response->getStatusCode(), [302, 422]);

        // Test with valid data after error
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
    }
}
