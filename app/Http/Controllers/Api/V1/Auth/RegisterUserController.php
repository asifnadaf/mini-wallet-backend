<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\UserRegistrationService;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Log;
use App\Contracts\ApiResponseInterface;
use Exception;

class RegisterUserController extends BaseApiController
{
    public function __construct(
        private UserRegistrationService $registrationService,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse); // Initialize $apiResponse
    }

    public function register(RegisterUserRequest $request): JsonResponse
    {
        try {
            $user = $this->registrationService->register($request->validated());

            $tokenResult = $user->createToken('Personal Access Token');
            $accessToken = $tokenResult->plainTextToken;

            $userResource = new UserResource($user);

            return $this->created([
                'user' => $userResource,
                'access_token' => $accessToken,
            ], 'User registered successfully');
        } catch (Exception $e) {
            Log::error('User registration error: ' . $e->getMessage());

            return $this->error(
                message: 'An unexpected error occurred during registration',
                errors: $e->getMessage(),
                statusCode: 500
            );
        }
    }
}
