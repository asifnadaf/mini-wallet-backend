<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\ForgotPasswordEmailRequest;
use App\Http\Requests\ForgotPasswordVerifyTokenRequest;
use App\Models\User;
use App\Services\ForgotPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Contracts\ApiResponseInterface;

class ForgotPasswordController extends BaseApiController
{

    public function __construct(
        private ForgotPasswordService $forgotPasswordService,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse); // Initialize $apiResponse
    }


    public function emailToken(ForgotPasswordEmailRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->validated()['email'])->firstOrFail();

            $result = $this->forgotPasswordService->sendEmailToken($user);

            return $this->success($result['data'] ?? null, $result['message'] ?? 'Please check your email for an OTP');
        } catch (Exception $e) {
            Log::error('Forgot password email token error: ' . $e->getMessage());
            return $this->error(
                'Server error - Forgot password email token',
                errors: $e->getMessage(),
                statusCode: 500
            );
        }
    }

    public function verifyToken(ForgotPasswordVerifyTokenRequest $request): JsonResponse
    {
        try {
            $this->forgotPasswordService->verifyToken(
                $request->validated()['email'],
                $request->validated()['token']
            );

            return $this->success(null, 'You can now reset your password');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), statusCode: 400);
        }
    }
}
