<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;

class LoginController extends BaseApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->error(
                    message: 'Invalid credentials',
                    errors: ['email' => ['The provided credentials are incorrect.']],
                    statusCode: 401
                );
            }

            $tokenResult = $user->createToken('Personal Access Token');
            $accessToken = $tokenResult->plainTextToken;

            $userResource = new UserResource($user);

            return $this->success([
                'user' => $userResource,
                'access_token' => $accessToken,
            ], 'Login successful');
        } catch (\Exception $e) {
            Log::error('User login error: ' . $e->getMessage());

            return $this->error(
                message: 'An unexpected error occurred during login',
                errors: $e->getMessage(),
                statusCode: 500
            );
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return $this->success(null, 'Logged out successfully');
        } catch (Exception $e) {
            Log::error('User logout error: ' . $e->getMessage());

            return $this->error(
                message: 'An unexpected error occurred during logout',
                errors: $e->getMessage(),
                statusCode: 500
            );
        }
    }
}
