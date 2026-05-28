<?php

namespace App\Services;

class TrackingService
{
    /**
     * Track status of operations.
     * 
     * @param string $operationId
     * @return array
     */
    public function getStatus(string $operationId): array
    {
        return [
            'id' => $operationId,
            'status' => 'routing'
        ];
    }
}
