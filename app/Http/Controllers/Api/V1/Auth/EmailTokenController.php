<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\EmailTokenService;
use App\Http\Requests\VerifyEmailTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Contracts\ApiResponseInterface;

class EmailTokenController extends BaseApiController
{
    public function __construct(
        private EmailTokenService $emailTokenService,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse); // Initialize $apiResponse
    }


    public function sendToken(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $response = $this->emailTokenService->send($request->user());

            return $response['success']
                ? $this->success(message: $response['message'])
                : $this->error(message: $response['message'], statusCode: $response['status_code']);
        } catch (Exception $e) {
            Log::error('EmailTokenController@sendToken error: ' . $e->getMessage());

            return $this->error('Server error - Email send token', $e->getMessage(), 500);
        }
    }

    public function verifyToken(VerifyEmailTokenRequest $request): JsonResponse
    {
        try {
            $response = $this->emailTokenService->verify($request->user(), $request->validated());

            return $response['success']
                ? $this->success(data: $response['data'] ?? null, message: $response['message'])
                : $this->error(message: $response['message'], statusCode: $response['status_code']);
        } catch (Exception $e) {
            Log::error('EmailTokenController@verifyToken error: ' . $e->getMessage());

            return $this->error('Server error - Email verify token', $e->getMessage(), 500);
        }
    }
}
