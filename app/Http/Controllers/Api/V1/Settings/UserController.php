<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class UserController extends BaseApiController
{
    /**
     * Show the authenticated user
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->error('Unauthenticated', statusCode: 401);
            }

            return $this->success(
                ['user' => new UserResource($user)],
                'User retrieved successfully'
            );
        } catch (Exception $e) {
            Log::error('UserController@show error: ' . $e->getMessage());

            return $this->error(
                'An unexpected error occurred while fetching the user',
                errors: $e->getMessage(),
                statusCode: 500
            );
        }
    }
}
