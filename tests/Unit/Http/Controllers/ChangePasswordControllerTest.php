<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Settings\ChangePasswordController;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Services\ChangePasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ChangePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_controller_can_be_instantiated()
    {
        $controller = app(ChangePasswordController::class);
        $this->assertInstanceOf(ChangePasswordController::class, $controller);
    }

    public function test_change_password_controller_has_required_methods()
    {
        $controller = app(ChangePasswordController::class);

        $this->assertTrue(method_exists($controller, 'store'));
    }

    public function test_store_returns_successful_response()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $mockChangePasswordService = Mockery::mock(ChangePasswordService::class);
        $mockChangePasswordService->shouldReceive('change')
            ->with(Mockery::on(function ($user) {
                return $user instanceof User && $user->email === 'john@example.com';
            }), Mockery::on(function ($data) {
                return isset($data['old_password']) && isset($data['new_password']);
            }))
            ->once();

        $this->app->instance(ChangePasswordService::class, $mockChangePasswordService);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('The password has been updated successfully.', $response->json('message'));
    }

    public function test_store_handles_validation_exception()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $mockChangePasswordService = Mockery::mock(ChangePasswordService::class);
        $mockChangePasswordService->shouldReceive('change')
            ->andThrow(new ValidationException(
                validator: app('validator')->make([], []),
                errorBag: 'default'
            ));

        $this->app->instance(ChangePasswordService::class, $mockChangePasswordService);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Validation failed', $response->json('message'));
    }

    public function test_store_handles_general_exception()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $mockChangePasswordService = Mockery::mock(ChangePasswordService::class);
        $mockChangePasswordService->shouldReceive('change')
            ->andThrow(new \Exception('Database connection failed'));

        $this->app->instance(ChangePasswordService::class, $mockChangePasswordService);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('An unexpected error occurred while changing the password', $response->json('message'));
    }

    public function test_store_requires_authentication()
    {
        $response = $this->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_requires_email_verification()
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

    public function test_store_validates_request_data()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Test missing old_password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());

        // Test missing new_password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());

        // Test same old and new password
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'oldpassword123',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_store_response_structure_consistency()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
            'email_verified_at' => now(),
        ]);

        $mockChangePasswordService = Mockery::mock(ChangePasswordService::class);
        $mockChangePasswordService->shouldReceive('change')
            ->once();

        $this->app->instance(ChangePasswordService::class, $mockChangePasswordService);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/change-password', [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        // Verify response structure consistency
        $responseData = $response->json();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);

        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('The password has been updated successfully.', $responseData['message']);
    }
}
