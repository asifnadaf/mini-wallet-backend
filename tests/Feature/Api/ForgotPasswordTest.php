<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_email_token_success()
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
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent to email john@example.com. Please verify email.',
            ]);

        // Verify token was created in database
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_send_email_token_user_not_found()
    {
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Server error - Forgot password email token', $response->json('message'));
    }

    public function test_send_email_token_validation_errors()
    {
        // Test with missing email
        $response = $this->postJson('/api/v1/forgot-password/email/token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);

        // Test with invalid email
        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'invalid-email'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);
    }

    public function test_verify_token_success()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // First send a token
        $sendResponse = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse->assertStatus(200);

        // Get the token from database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        $this->assertNotNull($tokenRecord);

        // Verify the token
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'You can now reset your password',
            ]);
    }

    public function test_verify_token_validation_errors()
    {
        // Test with missing email
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'token' => '123456'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);

        // Test with missing token
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'token'
                ]
            ]);

        // Test with invalid email
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'invalid-email',
            'token' => '123456'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);
    }

    public function test_verify_token_invalid_token()
    {
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => 'invalid-token'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
    }

    public function test_reset_password_success()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        // First send a token
        $sendResponse = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse->assertStatus(200);

        // Get the token from database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        $this->assertNotNull($tokenRecord);

        // Reset the password
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token,
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Your password has been reset successfully',
            ]);

        // Verify password was updated
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        // Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
            'token' => $tokenRecord->token,
        ]);
    }

    public function test_reset_password_validation_errors()
    {
        // Test with missing email
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'token' => '123456',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);

        // Test with missing token
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'token'
                ]
            ]);

        // Test with missing password
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'password'
                ]
            ]);

        // Test with invalid email
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'invalid-email',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);
    }

    public function test_reset_password_invalid_token()
    {
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
    }

    public function test_reset_password_user_not_found()
    {
        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'nonexistent@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
    }

    public function test_forgot_password_content_type()
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

    public function test_forgot_password_with_different_http_methods()
    {
        // Test GET method
        $response = $this->getJson('/api/v1/forgot-password/email/token');
        $response->assertStatus(405);

        $response = $this->getJson('/api/v1/forgot-password/verify/token');
        $response->assertStatus(405);

        $response = $this->getJson('/api/v1/forgot-password/reset-password');
        $response->assertStatus(405);

        // Test PUT method
        $response = $this->putJson('/api/v1/forgot-password/email/token');
        $response->assertStatus(405);

        $response = $this->putJson('/api/v1/forgot-password/verify/token');
        $response->assertStatus(405);

        $response = $this->putJson('/api/v1/forgot-password/reset-password');
        $response->assertStatus(405);

        // Test DELETE method
        $response = $this->deleteJson('/api/v1/forgot-password/email/token');
        $response->assertStatus(405);

        $response = $this->deleteJson('/api/v1/forgot-password/verify/token');
        $response->assertStatus(405);

        $response = $this->deleteJson('/api/v1/forgot-password/reset-password');
        $response->assertStatus(405);
    }

    public function test_forgot_password_response_consistency()
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

        // Verify response structure consistency
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);

        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertNull($responseData['data']);
    }
}
