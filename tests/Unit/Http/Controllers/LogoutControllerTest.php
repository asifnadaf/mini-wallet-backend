<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_controller_can_be_instantiated()
    {
        $controller = app(LoginController::class);
        $this->assertInstanceOf(LoginController::class, $controller);
    }

    public function test_logout_controller_has_required_methods()
    {
        $controller = app(LoginController::class);

        $this->assertTrue(method_exists($controller, 'logout'));
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
        $this->assertNull($response->json('data'));
    }

    public function test_logout_deletes_user_tokens()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Verify token exists before logout
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_deletes_all_user_tokens()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create multiple tokens
        $token1 = $user->createToken('test-token-1')->plainTextToken;
        $token2 = $user->createToken('test-token-2')->plainTextToken;

        // Verify tokens exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Verify all tokens were deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_returns_correct_response_structure()
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

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertNull($responseData['data']);
    }
}
