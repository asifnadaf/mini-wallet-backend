<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_user_success()
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

        $this->assertEquals($user->id, $response->json('data.user.id'));
        $this->assertEquals($user->name, $response->json('data.user.name'));
        $this->assertEquals($user->email, $response->json('data.user.email'));
    }

    public function test_get_user_requires_authentication()
    {
        $response = $this->getJson('/api/v1/user');
        $response->assertStatus(401);
    }

    public function test_get_user_with_verified_email()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user');

        $response->assertStatus(200);

        $userData = $response->json('data.user');
        $this->assertArrayHasKey('email_verified_at', $userData);
    }

    public function test_get_user_with_unverified_email()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user');

        $response->assertStatus(200);

        $userData = $response->json('data.user');
        $this->assertNull($userData['email_verified_at']);
    }

    public function test_get_user_excludes_sensitive_data()
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

    public function test_get_user_with_single_user()
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
        $this->assertEquals($user->name, $userData['name']);
        $this->assertEquals($user->email, $userData['email']);
    }

    public function test_get_user_content_type()
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
            ->assertHeader('content-type', 'application/json');
    }

    public function test_get_user_with_different_http_methods()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Test POST method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/user');
        $response->assertStatus(405);

        // Test PUT method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/v1/user');
        $response->assertStatus(405);

        // Test DELETE method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/user');
        $response->assertStatus(405);

        // Test PATCH method
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/v1/user');
        $response->assertStatus(405);
    }

    public function test_get_user_response_consistency()
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

        // Verify response structure consistency
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);

        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('User retrieved successfully', $responseData['message']);
    }

    public function test_get_user_with_invalid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_get_user_without_authorization_header()
    {
        $response = $this->getJson('/api/v1/user');
        $response->assertStatus(401);
    }

    public function test_get_user_with_malformed_authorization_header()
    {
        $response = $this->withHeaders([
            'Authorization' => 'InvalidFormat token123'
        ])->getJson('/api/v1/user');

        $response->assertStatus(401);
    }
}
