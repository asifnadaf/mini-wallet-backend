<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;

abstract class BaseApiController extends Controller
{
    protected ApiResponseInterface $apiResponse;

    public function __construct(ApiResponseInterface $apiResponse)
    {
        $this->apiResponse = $apiResponse;
    }

    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200)
    {
        return $this->apiResponse->success($data, $message, $statusCode);
    }

    protected function error(string $message = 'Error', mixed $errors = null, int $statusCode = 500)
    {
        return $this->apiResponse->error($message, $errors, $statusCode);
    }

    protected function created(mixed $data = null, string $message = 'Resource created successfully')
    {
        return $this->apiResponse->created($data, $message);
    }

    protected function updated(mixed $data = null, string $message = 'Resource updated successfully')
    {
        return $this->apiResponse->updated($data, $message);
    }

    protected function deleted(string $message = 'Resource deleted successfully')
    {
        return $this->apiResponse->deleted($message);
    }

    protected function validationError(mixed $errors, string $message = 'Validation failed')
    {
        return $this->apiResponse->validationError($errors, $message);
    }

    protected function unauthorized(string $message = 'Unauthorized')
    {
        return $this->apiResponse->unauthorized($message);
    }

    protected function paginated(mixed $data, string $message = 'Data retrieved successfully', int $statusCode = 200)
    {
        return $this->apiResponse->paginated($data, $message, $statusCode);
    }
}
