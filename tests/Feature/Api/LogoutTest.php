<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_success()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create a token for the user
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

        // Verify tokens exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/logout');

        $response->assertStatus(405);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/v1/logout');

        $response->assertStatus(405);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/logout');

        $response->assertStatus(405);
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

        $loginResponse2 = $this->postJson('/api/v1/login', $loginData);
        $loginResponse2->assertStatus(200);

        // Get one of the tokens
        $token = $loginResponse1->json('data.access_token');

        // Logout with one token should delete all tokens
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify all tokens were deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }
}
