<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\RegisterUserController;
use App\Services\UserRegistrationService;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Contracts\ApiResponseInterface;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;
use Exception;

class RegisterUserControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_returns_successful_response()
    {
        $mockRegistrationService = Mockery::mock(UserRegistrationService::class);
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $mockRequest = Mockery::mock(RegisterUserRequest::class);

        $mockUser = Mockery::mock(User::class);
        $mockUser->shouldReceive('createToken')
            ->with('Personal Access Token')
            ->once()
            ->andReturnSelf();

        $mockUser->shouldReceive('getAttribute')
            ->with('plainTextToken')
            ->andReturn('test-token');

        // Mock user attributes for UserResource
        $mockUser->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);
        $mockUser->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('John Doe');
        $mockUser->shouldReceive('getAttribute')
            ->with('email')
            ->andReturn('john@example.com');
        $mockUser->shouldReceive('getAttribute')
            ->with('email_verified_at')
            ->andReturn(null);

        $validatedData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $expectedResponse = response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($mockUser),
                'access_token' => 'test-token',
            ]
        ], 201);

        $mockRequest->shouldReceive('validated')
            ->once()
            ->andReturn($validatedData);

        $mockRegistrationService->shouldReceive('register')
            ->with($validatedData)
            ->once()
            ->andReturn($mockUser);

        $mockApiResponse->shouldReceive('created')
            ->with(Mockery::type('array'), 'User registered successfully')
            ->once()
            ->andReturn($expectedResponse);

        $controller = new RegisterUserController(
            $mockRegistrationService,
            $mockApiResponse
        );

        $result = $controller->register($mockRequest);

        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    public function test_register_handles_exception()
    {
        $mockRegistrationService = Mockery::mock(UserRegistrationService::class);
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $mockRequest = Mockery::mock(RegisterUserRequest::class);

        $validatedData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $exception = new Exception('Registration failed');
        $expectedErrorResponse = response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred during registration',
            'errors' => 'Registration failed'
        ], 500);

        $mockRequest->shouldReceive('validated')
            ->once()
            ->andReturn($validatedData);

        $mockRegistrationService->shouldReceive('register')
            ->with($validatedData)
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->with('User registration error: Registration failed')
            ->once();

        $mockApiResponse->shouldReceive('error')
            ->with('An unexpected error occurred during registration', 'Registration failed', 500)
            ->once()
            ->andReturn($expectedErrorResponse);

        $controller = new RegisterUserController(
            $mockRegistrationService,
            $mockApiResponse
        );

        $result = $controller->register($mockRequest);

        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    public function test_register_logs_error_on_exception()
    {
        $mockRegistrationService = Mockery::mock(UserRegistrationService::class);
        $mockApiResponse = Mockery::mock(ApiResponseInterface::class);
        $mockRequest = Mockery::mock(RegisterUserRequest::class);

        $validatedData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $exception = new Exception('Database connection failed');

        $mockRequest->shouldReceive('validated')
            ->once()
            ->andReturn($validatedData);

        $mockRegistrationService->shouldReceive('register')
            ->with($validatedData)
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->with('User registration error: Database connection failed')
            ->once();

        $mockApiResponse->shouldReceive('error')
            ->with('An unexpected error occurred during registration', 'Database connection failed', 500)
            ->once()
            ->andReturn(response()->json(['error' => true], 500));

        $controller = new RegisterUserController(
            $mockRegistrationService,
            $mockApiResponse
        );

        $result = $controller->register($mockRequest);

        // Verify the method was called and returned a response
        $this->assertInstanceOf(JsonResponse::class, $result);
    }
}
