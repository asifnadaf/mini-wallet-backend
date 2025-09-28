<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Services\ResetPasswordService;
use App\Contracts\ApiResponseInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Mockery;

class ResetPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reset_password_controller_can_be_instantiated()
    {
        $controller = app(ResetPasswordController::class);
        $this->assertInstanceOf(ResetPasswordController::class, $controller);
    }

    public function test_reset_password_controller_has_required_methods()
    {
        $controller = app(ResetPasswordController::class);

        $this->assertTrue(method_exists($controller, 'resetPassword'));
    }

    public function test_reset_password_returns_successful_response()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $mockResetPasswordService = Mockery::mock(ResetPasswordService::class);
        $mockResetPasswordService->shouldReceive('reset')
            ->with([
                'email' => 'john@example.com',
                'token' => '123456',
                'password' => 'newpassword123'
            ])
            ->once();

        $this->app->instance(ResetPasswordService::class, $mockResetPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Your password has been reset successfully', $response->json('message'));
    }

    public function test_reset_password_handles_invalid_token()
    {
        $mockResetPasswordService = Mockery::mock(ResetPasswordService::class);
        $mockResetPasswordService->shouldReceive('reset')
            ->with([
                'email' => 'john@example.com',
                'token' => 'invalid-token',
                'password' => 'newpassword123'
            ])
            ->once()
            ->andThrow(new \Exception('Invalid token'));

        $this->app->instance(ResetPasswordService::class, $mockResetPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Invalid token', $response->json('message'));
    }

    public function test_reset_password_handles_expired_token()
    {
        $mockResetPasswordService = Mockery::mock(ResetPasswordService::class);
        $mockResetPasswordService->shouldReceive('reset')
            ->with([
                'email' => 'john@example.com',
                'token' => 'expired-token',
                'password' => 'newpassword123'
            ])
            ->once()
            ->andThrow(new \Exception('Token expired'));

        $this->app->instance(ResetPasswordService::class, $mockResetPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => 'expired-token',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Token expired', $response->json('message'));
    }

    public function test_reset_password_handles_user_not_found()
    {
        $mockResetPasswordService = Mockery::mock(ResetPasswordService::class);
        $mockResetPasswordService->shouldReceive('reset')
            ->with([
                'email' => 'nonexistent@example.com',
                'token' => '123456',
                'password' => 'newpassword123'
            ])
            ->once()
            ->andThrow(new \Exception('User not found'));

        $this->app->instance(ResetPasswordService::class, $mockResetPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'nonexistent@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('User not found', $response->json('message'));
    }

    public function test_reset_password_handles_service_exception()
    {
        $mockResetPasswordService = Mockery::mock(ResetPasswordService::class);
        $mockResetPasswordService->shouldReceive('reset')
            ->with([
                'email' => 'john@example.com',
                'token' => '123456',
                'password' => 'newpassword123'
            ])
            ->once()
            ->andThrow(new \Exception('Database error'));

        $this->app->instance(ResetPasswordService::class, $mockResetPasswordService);

        $response = $this->postJson('/api/v1/forgot-password/reset-password', [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Database error', $response->json('message'));
    }
}
