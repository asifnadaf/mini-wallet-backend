<?php

namespace App\Services;

use App\Contracts\ApiResponseInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

class ApiResponseService implements ApiResponseInterface
{
    public function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($response, $statusCode);
    }

    public function error(
        string $message = 'Error',
        mixed $errors = null,
        int $statusCode = 500
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        return response()->json($response, $statusCode);
    }

    public function paginated(
        mixed $data,
        string $message = 'Data retrieved successfully',
        int $statusCode = 200
    ): JsonResponse {
        if ($data instanceof ResourceCollection) {
            $data = $data->response()->getData(true);
        } elseif ($data instanceof AbstractPaginator) {
            $data = $data->toArray();
        }

        return $this->success($data, $message, $statusCode);
    }

    public function created(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return $this->success($data, $message, 201);
    }

    public function updated(
        mixed $data = null,
        string $message = 'Resource updated successfully'
    ): JsonResponse {
        return $this->success($data, $message, 200);
    }

    public function deleted(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->success(null, $message, 200);
    }

    public function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    public function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, $errors, 422);
    }

    public function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, null, 404);
    }

    public function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, null, 401);
    }

    public function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, null, 403);
    }
}
