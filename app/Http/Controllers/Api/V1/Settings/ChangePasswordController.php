<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\ChangePasswordRequest;
use App\Services\ChangePasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Contracts\ApiResponseInterface;

class ChangePasswordController extends BaseApiController
{
    public function __construct(
        private ChangePasswordService $changePasswordService,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse); // Initialize $apiResponse
    }

    /**
     * Handle an incoming change password request.
     */
    public function store(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->changePasswordService->change(
                $request->user(),
                $request->validated()
            );

            return $this->success(
                message: 'The password has been updated successfully.'
            );
        } catch (ValidationException $e) {
            // Explicitly catch validation errors (wrong old password, rule fails, etc.)
            return $this->error(
                message: 'Validation failed',
                errors: $e->errors(),
                statusCode: 422
            );
        } catch (Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());

            return $this->error(
                message: 'An unexpected error occurred while changing the password',
                errors: config('app.debug') ? $e->getMessage() : null,
                statusCode: 500
            );
        }
    }
}
