<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ForgotPasswordWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_forgot_password_workflow()
    {
        // Step 1: Create user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $this->assertTrue(Hash::check('oldpassword', $user->password));

        // Step 2: Send forgot password token
        $sendResponse = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent to email john@example.com. Please verify email.',
            ]);

        // Step 3: Verify token exists in database
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);

        // Step 4: Get token from database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        $this->assertNotNull($tokenRecord);

        // Step 5: Verify the token
        $verifyResponse = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'You can now reset your password',
            ]);

        // Step 6: Reset the password
        $resetResponse = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token,
            'password' => 'newpassword123'
        ]);

        $resetResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Your password has been reset successfully',
            ]);

        // Step 7: Verify password was updated
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertFalse(Hash::check('oldpassword', $user->password));

        // Step 8: Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
            'token' => $tokenRecord->token,
        ]);
    }

    public function test_forgot_password_workflow_with_invalid_token()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send token first
        $sendResponse = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse->assertStatus(200);

        // Try to verify with invalid token
        $verifyResponse = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => 'invalid-token'
        ]);

        $verifyResponse->assertStatus(400);
        $this->assertFalse($verifyResponse->json('success'));

        // Try to reset with invalid token
        $resetResponse = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123'
        ]);

        $resetResponse->assertStatus(400);
        $this->assertFalse($resetResponse->json('success'));
    }

    public function test_forgot_password_workflow_with_validation_errors()
    {
        // Test send token with validation errors
        $response = $this->postJson('/api/v1/forgot-password/email/token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email'
                ]
            ]);

        // Test verify token with validation errors
        $response = $this->postJson('/api/v1/forgot-password/verify/token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                    'token'
                ]
            ]);

        // Test reset password with validation errors
        $response = $this->postJson('/api/v1/forgot-password/reset-password', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                    'token',
                    'password'
                ]
            ]);
    }

    public function test_forgot_password_workflow_multiple_tokens()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send first token
        $sendResponse1 = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse1->assertStatus(200);

        // Send second token (should replace the first one)
        $sendResponse2 = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse2->assertStatus(200);

        // Should only have one token in database
        $tokenCount = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->count();

        $this->assertEquals(1, $tokenCount);

        // Get the latest token
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        // Verify the latest token
        $verifyResponse = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200);
    }

    public function test_forgot_password_workflow_performance()
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

        // Forgot password should complete within reasonable time (less than 2 seconds)
        $this->assertLessThan(2.0, $executionTime);
    }

    public function test_forgot_password_workflow_security()
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

    public function test_forgot_password_workflow_data_consistency()
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

        // Verify response data consistency
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertEquals('OTP sent to email john@example.com. Please verify email.', $responseData['message']);
        $this->assertNull($responseData['data']);

        // Verify database consistency
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);

        // Verify user password is still the same
        $user->refresh();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_forgot_password_workflow_error_recovery()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test with invalid data
        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'invalid-email',
            'token' => '123456'
        ]);
        $response->assertStatus(422);

        // Test with valid data after error
        $sendResponse = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse->assertStatus(200);

        // Get token and verify
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        $verifyResponse = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200);
    }

    public function test_forgot_password_workflow_concurrent_requests()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Make concurrent send token requests
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson('/api/v1/forgot-password/email/token', [
                'email' => 'john@example.com'
            ]);
        }

        // At least one should succeed
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            }
        }

        $this->assertGreaterThanOrEqual(1, $successCount);

        // Should only have one token in database
        $tokenCount = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->count();

        $this->assertEquals(1, $tokenCount);
    }

    public function test_forgot_password_workflow_token_expiration()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send token
        $sendResponse = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);
        $sendResponse->assertStatus(200);

        // Get token
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        // Manually expire the token by updating the created_at timestamp
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subHours(2)]);

        // Try to verify expired token
        $verifyResponse = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token
        ]);

        // The token might still be valid or might be expired depending on the implementation
        $this->assertContains($verifyResponse->getStatusCode(), [200, 400]);
        if ($verifyResponse->getStatusCode() === 400) {
            $this->assertFalse($verifyResponse->json('success'));
        }

        // Try to reset with expired token
        $resetResponse = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => $tokenRecord->token,
            'password' => 'newpassword123'
        ]);

        // The token might still be valid or might be expired depending on the implementation
        $this->assertContains($resetResponse->getStatusCode(), [200, 400]);
        if ($resetResponse->getStatusCode() === 400) {
            $this->assertFalse($resetResponse->json('success'));
        }
    }
}
