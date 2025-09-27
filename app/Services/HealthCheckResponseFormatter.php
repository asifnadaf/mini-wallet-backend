<?php

namespace App\Services;

use App\Contracts\ApiResponseInterface;
use App\Enums\ServerStatus;
use Illuminate\Support\Collection;

class HealthCheckResponseFormatter
{
    public function __construct(private ApiResponseInterface $apiResponse) {}

    public function format(Collection $healthStatus): mixed
    {
        $isOverallHealthy = $healthStatus->every(function ($status) {
            return $status === ServerStatus::UP;
        });

        $data = [
            'status' => $isOverallHealthy ? 'healthy' : 'unhealthy',
            'services' => $healthStatus->map(function ($status, $service) {
                return [
                    'service' => $service,
                    'status' => $status->value,
                    'last_checked' => now()->format('Y-m-d H:i:s')
                ];
            })->values()->toArray(),
        ];

        $message = $isOverallHealthy
            ? 'All services are operational'
            : 'Some services are experiencing issues';

        return $this->apiResponse->success($data, $message);
    }
}
