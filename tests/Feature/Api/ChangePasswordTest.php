<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_success()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'The password has been updated successfully.',
            ]);

        // Verify password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertFalse(Hash::check('oldpassword123', $user->password));
    }

    public function test_change_password_requires_authentication()
    {
        $response = $this->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_requires_email_verification()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => null, // Unverified email
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(409);
    }

    public function test_change_password_with_wrong_old_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'old_password'
                ]
            ]);

        // Verify password was not changed
        $user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $user->password));
        $this->assertFalse(Hash::check('newpassword123', $user->password));
    }

    public function test_change_password_with_same_old_and_new_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('samepassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'samepassword123',
            'new_password' => 'samepassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'new_password'
                ]
            ]);
    }

    public function test_change_password_with_missing_old_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'old_password'
                ]
            ]);
    }

    public function test_change_password_with_missing_new_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'new_password'
                ]
            ]);
    }

    public function test_change_password_with_empty_old_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => '',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'old_password'
                ]
            ]);
    }

    public function test_change_password_with_empty_new_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'new_password'
                ]
            ]);
    }

    public function test_change_password_with_invalid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_without_authorization_header()
    {
        $response = $this->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_with_malformed_authorization_header()
    {
        $response = $this->withHeaders([
            'Authorization' => 'InvalidFormat token',
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_response_structure()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ]);

        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertTrue($responseData['success']);
    }

    public function test_change_password_with_special_characters()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('OldP@ss123!'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'OldP@ss123!',
            'new_password' => 'N3wP@ss456$',
        ]);

        $response->assertStatus(200);

        // Verify password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check('N3wP@ss456$', $user->password));
        $this->assertFalse(Hash::check('OldP@ss123!', $user->password));
    }

    public function test_change_password_with_long_password()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('short123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $longPassword = str_repeat('a', 50) . '123!@#';
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'short123',
            'new_password' => $longPassword,
        ]);

        $response->assertStatus(200);

        // Verify password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check($longPassword, $user->password));
        $this->assertFalse(Hash::check('short123', $user->password));
    }
}
