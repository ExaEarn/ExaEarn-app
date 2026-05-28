<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;

class AdaptiveLimitingService
{
    /**
     * Adjust limit based on risk level and behavior patterns.
     */
    public function getAdjustedLimit(string $endpoint, string $identifier, string $riskLevel): int
    {
        $baseLimit = $this->getBaseLimit($endpoint);

        return match ($riskLevel) {
            'blocked' => 0,
            'suspicious' => (int) ($baseLimit * 0.4),  // 40% of normal
            'default' => $baseLimit,
        };
    }

    /**
     * Get base limit for endpoint.
     */
    private function getBaseLimit(string $endpoint): int
    {
        $config = config("security-ratelimit.limits.{$endpoint}", []);
        return (int) ($config['default'] ?? 60);
    }

    /**
     * Reduce limits for identifier temporarily.
     */
    public function reduceLimits(string $identifier, float $factor = 0.5, int $durationSeconds = 300): void
    {
        Cache::put("limit_reduction:{$identifier}", $factor, $durationSeconds);
    }

    /**
     * Check if limits are reduced for identifier.
     */
    public function hasReducedLimits(string $identifier): bool
    {
        return (bool) Cache::get("limit_reduction:{$identifier}");
    }

    /**
     * Get limit reduction factor.
     */
    public function getReductionFactor(string $identifier): float
    {
        return Cache::get("limit_reduction:{$identifier}", 1.0);
    }

    /**
     * Remove limit reduction.
     */
    public function removeLimitReduction(string $identifier): void
    {
        Cache::forget("limit_reduction:{$identifier}");
    }

    /**
     * Apply temporary hold on endpoint for identifier.
     */
    public function holdEndpoint(string $identifier, string $endpoint, int $durationSeconds = 60): void
    {
        Cache::put("endpoint_hold:{$identifier}:{$endpoint}", true, $durationSeconds);
    }

    /**
     * Check if endpoint is on hold.
     */
    public function isEndpointOnHold(string $identifier, string $endpoint): bool
    {
        return (bool) Cache::get("endpoint_hold:{$identifier}:{$endpoint}");
    }
}
