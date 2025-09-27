<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\HealthCheckResponseFormatter;
use App\Services\HealthCheck\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Contracts\ApiResponseInterface;

class ServerStatusController extends BaseApiController
{
    public function __construct(
        private HealthCheckService $healthCheckService,
        private HealthCheckResponseFormatter $healthFormatter,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse); // Initialize $apiResponse
    }


    /**
     * Health check endpoint
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $healthStatus = $this->healthCheckService->getHealthStatus();
            return $this->healthFormatter->format($healthStatus);
        } catch (\Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());

            return $this->error(
                message: 'Unable to complete health check',
                errors: $e->getMessage(),
                statusCode: 503
            );
        }
    }
}
