<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimpleUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/v1/user');
        $response->assertStatus(401);
    }

    public function test_user_endpoint_with_valid_token()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                    ]
                ]
            ]);

        $userData = $response->json('data.user');
        $this->assertEquals($user->id, $userData['id']);
        $this->assertEquals($user->name, $userData['name']);
        $this->assertEquals($user->email, $userData['email']);
    }

    public function test_user_endpoint_with_invalid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_user_endpoint_excludes_sensitive_data()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user');

        $response->assertStatus(200);

        $userData = $response->json('data.user');

        // Verify sensitive data is not included
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
        $this->assertArrayNotHasKey('created_at', $userData);
        $this->assertArrayNotHasKey('updated_at', $userData);
    }
}
