<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\ResetPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Contracts\ApiResponseInterface;

class ResetPasswordController extends BaseApiController
{

    public function __construct(
        private ResetPasswordService $resetPasswordService,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse); // Initialize $apiResponse
    }


    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->resetPasswordService->reset($request->validated());

            return $this->success(null, 'Your password has been reset successfully');
        } catch (Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());

            return $this->error(
                message: $e->getMessage() ?? 'An unexpected error occurred during password reset',
                errors: config('app.debug') ? $e->getMessage() : null,
                statusCode: 400
            );
        }
    }
}
