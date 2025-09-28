<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Services\ForgotPasswordService;
use App\Contracts\ApiResponseInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Mockery;

class ForgotPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_forgot_password_controller_can_be_instantiated()
    {
        $controller = app(ForgotPasswordController::class);
        $this->assertInstanceOf(ForgotPasswordController::class, $controller);
    }

    public function test_forgot_password_controller_has_required_methods()
    {
        $controller = app(ForgotPasswordController::class);

        $this->assertTrue(method_exists($controller, 'emailToken'));
        $this->assertTrue(method_exists($controller, 'verifyToken'));
    }

    public function test_email_token_returns_successful_response()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockForgotPasswordService = Mockery::mock(ForgotPasswordService::class);
        $mockForgotPasswordService->shouldReceive('sendEmailToken')
            ->with(Mockery::on(function ($user) {
                return $user instanceof User && $user->email === 'john@example.com';
            }))
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'OTP sent to email john@example.com. Please verify email.',
                'data' => null,
            ]);

        $this->app->instance(ForgotPasswordService::class, $mockForgotPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('OTP sent to email john@example.com. Please verify email.', $response->json('message'));
    }

    public function test_email_token_handles_user_not_found()
    {
        $mockForgotPasswordService = Mockery::mock(ForgotPasswordService::class);
        $mockForgotPasswordService->shouldNotReceive('sendEmailToken');

        $this->app->instance(ForgotPasswordService::class, $mockForgotPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Server error - Forgot password email token', $response->json('message'));
    }

    public function test_email_token_handles_service_exception()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $mockForgotPasswordService = Mockery::mock(ForgotPasswordService::class);
        $mockForgotPasswordService->shouldReceive('sendEmailToken')
            ->with(Mockery::on(function ($user) {
                return $user instanceof User && $user->email === 'john@example.com';
            }))
            ->once()
            ->andThrow(new \Exception('Service error'));

        $this->app->instance(ForgotPasswordService::class, $mockForgotPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/email/token', [
            'email' => 'john@example.com'
        ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Server error - Forgot password email token', $response->json('message'));
    }

    public function test_verify_token_returns_successful_response()
    {
        $mockForgotPasswordService = Mockery::mock(ForgotPasswordService::class);
        $mockForgotPasswordService->shouldReceive('verifyToken')
            ->with('john@example.com', '123456')
            ->once()
            ->andReturn(true);

        $this->app->instance(ForgotPasswordService::class, $mockForgotPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => '123456'
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('You can now reset your password', $response->json('message'));
    }

    public function test_verify_token_handles_invalid_token()
    {
        $mockForgotPasswordService = Mockery::mock(ForgotPasswordService::class);
        $mockForgotPasswordService->shouldReceive('verifyToken')
            ->with('john@example.com', 'invalid-token')
            ->once()
            ->andThrow(new \Exception('Invalid token'));

        $this->app->instance(ForgotPasswordService::class, $mockForgotPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => 'invalid-token'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Invalid token', $response->json('message'));
    }

    public function test_verify_token_handles_expired_token()
    {
        $mockForgotPasswordService = Mockery::mock(ForgotPasswordService::class);
        $mockForgotPasswordService->shouldReceive('verifyToken')
            ->with('john@example.com', 'expired-token')
            ->once()
            ->andThrow(new \Exception('Token expired'));

        $this->app->instance(ForgotPasswordService::class, $mockForgotPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/verify/token', [
            'email' => 'john@example.com',
            'token' => 'expired-token'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Token expired', $response->json('message'));
    }
}
