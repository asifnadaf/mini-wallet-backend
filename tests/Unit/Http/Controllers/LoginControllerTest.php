<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_controller_can_be_instantiated()
    {
        $controller = app(LoginController::class);
        $this->assertInstanceOf(LoginController::class, $controller);
    }

    public function test_login_controller_has_required_methods()
    {
        $controller = app(LoginController::class);

        $this->assertTrue(method_exists($controller, 'login'));
        $this->assertTrue(method_exists($controller, 'logout'));
    }

    public function test_login_method_accepts_login_request()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Login successful', $response->json('message'));
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('user', $response->json('data'));
        $this->assertArrayHasKey('access_token', $response->json('data'));
    }

    public function test_logout_method_requires_authentication()
    {
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    public function test_logout_method_works_with_valid_token()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Logged out successfully', $response->json('message'));
    }
}
