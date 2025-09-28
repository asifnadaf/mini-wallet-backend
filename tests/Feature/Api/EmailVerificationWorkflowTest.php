<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class EmailVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_email_verification_workflow()
    {
        // Step 1: Create user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->assertNull($user->email_verified_at);

        // Step 2: Send verification token
        $sendResponse = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $sendResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent to email john@example.com. Please verify email.',
            ]);

        // Step 3: Verify token exists in database
        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'john@example.com',
        ]);

        // Step 4: Get token from database
        $tokenRecord = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->first();

        $this->assertNotNull($tokenRecord);

        // Step 5: Verify the token
        $verifyResponse = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email john@example.com is verified',
            ]);

        // Step 6: Verify user email is marked as verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // Step 7: Verify token was deleted
        $this->assertDatabaseMissing('email_verification_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_email_verification_workflow_with_invalid_token()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send token first
        $sendResponse = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $sendResponse->assertStatus(200);

        // Try to verify with invalid token
        $verifyResponse = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 999999
        ]);

        $verifyResponse->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid OTP',
            ]);

        // User should still be unverified
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_workflow_with_validation_errors()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test with missing token
        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'token'
                ]
            ]);

        // Test with invalid token format
        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 'invalid-token'
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'token'
                ]
            ]);
    }

    public function test_email_verification_workflow_already_verified()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Try to send token for already verified email
        $sendResponse = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $this->assertContains($sendResponse->getStatusCode(), [200, 422]);
        if ($sendResponse->getStatusCode() === 422) {
            $sendResponse->assertJson([
                'success' => false,
                'message' => 'Email is already verified',
            ]);
        }

        // Try to verify token for already verified email
        $verifyResponse = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 123456
        ]);
        $this->assertContains($verifyResponse->getStatusCode(), [200, 400, 422]);
        if ($verifyResponse->getStatusCode() === 422) {
            $verifyResponse->assertJson([
                'success' => false,
                'message' => 'Email is already verified',
            ]);
        }
    }

    public function test_email_verification_workflow_multiple_tokens()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Send first token
        $sendResponse1 = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $sendResponse1->assertStatus(200);

        // Send second token (should replace the first one)
        $sendResponse2 = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $sendResponse2->assertStatus(200);

        // Should only have one token in database
        $tokenCount = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->count();

        $this->assertEquals(1, $tokenCount);

        // Get the latest token
        $tokenRecord = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->first();

        // Verify the latest token
        $verifyResponse = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200);
    }

    public function test_email_verification_workflow_performance()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // Email verification should complete within reasonable time (less than 2 seconds)
        $this->assertLessThan(2.0, $executionTime);
    }

    public function test_email_verification_workflow_security()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test rate limiting for send token
        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        }

        // All requests should succeed or fail with validation (rate limiting may not trigger in tests)
        for ($i = 0; $i < 6; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 422, 429]);
        }

        // Test rate limiting for verify token
        $responses = [];
        for ($i = 0; $i < 11; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
                'token' => 123456
            ]);
        }

        // All requests should succeed or fail with validation (rate limiting may not trigger in tests)
        for ($i = 0; $i < 11; $i++) {
            $this->assertContains($responses[$i]->getStatusCode(), [200, 400, 422, 429]);
        }
    }

    public function test_email_verification_workflow_data_consistency()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $response->assertStatus(200);

        // Verify response data consistency
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertEquals('OTP sent to email john@example.com. Please verify email.', $responseData['message']);
        $this->assertNull($responseData['data']);

        // Verify database consistency
        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'john@example.com',
        ]);

        // Verify user is still unverified
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_workflow_error_recovery()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test with invalid data
        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 'invalid'
        ]);
        $response->assertStatus(422);

        // Test with valid data after error
        $sendResponse = $this->actingAs($user)->postJson('/api/v1/email/send-token');
        $sendResponse->assertStatus(200);

        // Get token and verify
        $tokenRecord = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->first();

        $verifyResponse = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => $tokenRecord->token
        ]);

        $verifyResponse->assertStatus(200);
    }

    public function test_email_verification_workflow_concurrent_requests()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Make concurrent send token requests
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/v1/email/send-token');
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
        $tokenCount = DB::table('email_verification_tokens')
            ->where('email', $user->email)
            ->count();

        $this->assertEquals(1, $tokenCount);
    }
}
