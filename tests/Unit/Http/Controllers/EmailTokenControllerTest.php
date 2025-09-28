<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\EmailTokenController;
use App\Services\EmailTokenService;
use App\Contracts\ApiResponseInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Mockery;

class EmailTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_email_token_controller_can_be_instantiated()
    {
        $controller = app(EmailTokenController::class);
        $this->assertInstanceOf(EmailTokenController::class, $controller);
    }

    public function test_email_token_controller_has_required_methods()
    {
        $controller = app(EmailTokenController::class);

        $this->assertTrue(method_exists($controller, 'sendToken'));
        $this->assertTrue(method_exists($controller, 'verifyToken'));
    }

    public function test_send_token_returns_successful_response()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockEmailTokenService = Mockery::mock(EmailTokenService::class);
        $mockEmailTokenService->shouldReceive('send')
            ->with($user)
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'OTP sent to email john@example.com. Please verify email.',
                'status_code' => 200,
            ]);

        $this->app->instance(EmailTokenService::class, $mockEmailTokenService);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('OTP sent to email john@example.com. Please verify email.', $response->json('message'));
    }

    public function test_send_token_handles_service_error()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockEmailTokenService = Mockery::mock(EmailTokenService::class);
        $mockEmailTokenService->shouldReceive('send')
            ->with($user)
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Email is already verified',
                'status_code' => 422,
            ]);

        $this->app->instance(EmailTokenService::class, $mockEmailTokenService);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Email is already verified', $response->json('message'));
    }

    public function test_send_token_handles_exception()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockEmailTokenService = Mockery::mock(EmailTokenService::class);
        $mockEmailTokenService->shouldReceive('send')
            ->with($user)
            ->once()
            ->andThrow(new \Exception('Service error'));

        $this->app->instance(EmailTokenService::class, $mockEmailTokenService);

        $response = $this->actingAs($user)->postJson('/api/v1/email/send-token');

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Server error - Email send token', $response->json('message'));
    }

    public function test_verify_token_returns_successful_response()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockEmailTokenService = Mockery::mock(EmailTokenService::class);
        $mockEmailTokenService->shouldReceive('verify')
            ->with($user, ['token' => 123456])
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Email john@example.com is verified',
                'status_code' => 200,
            ]);

        $this->app->instance(EmailTokenService::class, $mockEmailTokenService);

        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 123456
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Email john@example.com is verified', $response->json('message'));
    }

    public function test_verify_token_handles_invalid_token()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockEmailTokenService = Mockery::mock(EmailTokenService::class);
        $mockEmailTokenService->shouldReceive('verify')
            ->with($user, ['token' => 123456])
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Invalid OTP',
                'status_code' => 400,
            ]);

        $this->app->instance(EmailTokenService::class, $mockEmailTokenService);

        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 123456
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Invalid OTP', $response->json('message'));
    }

    public function test_verify_token_handles_exception()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockEmailTokenService = Mockery::mock(EmailTokenService::class);
        $mockEmailTokenService->shouldReceive('verify')
            ->with($user, ['token' => 123456])
            ->once()
            ->andThrow(new \Exception('Service error'));

        $this->app->instance(EmailTokenService::class, $mockEmailTokenService);

        $response = $this->actingAs($user)->postJson('/api/v1/email/verify-token', [
            'token' => 123456
        ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Server error - Email verify token', $response->json('message'));
    }
}
