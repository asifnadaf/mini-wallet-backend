<?php

namespace App\Contracts;

use Illuminate\Http\JsonResponse;

interface ApiResponseInterface
{
    public function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse;

    public function error(
        string $message = 'Error',
        mixed $errors = null,
        int $statusCode = 500
    ): JsonResponse;

    public function paginated(
        mixed $data,
        string $message = 'Data retrieved successfully',
        int $statusCode = 200
    ): JsonResponse;

    public function created(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse;

    public function updated(
        mixed $data = null,
        string $message = 'Resource updated successfully'
    ): JsonResponse;

    public function deleted(
        string $message = 'Resource deleted successfully'
    ): JsonResponse;

    public function validationError(
        mixed $errors,
        string $message = 'Validation failed'
    ): JsonResponse;

    public function unauthorized(
        string $message = 'Unauthorized'
    ): JsonResponse;
}
