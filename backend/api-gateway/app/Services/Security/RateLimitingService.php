<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitingService
{
    private const SCALE = 8;

    public function __construct(
        private readonly AdaptiveLimitingService $adaptiveService,
        private readonly IPBlockingService $ipBlockingService,
        private readonly BotDetectionService $botDetectionService,
    ) {
    }

    /**
     * Check if request should be allowed based on rate limits.
     */
    public function checkRateLimit(
        string $endpoint,
        string $identifier, // IP or user_id
        ?string $userAgent = null,
        ?string $ip = null
    ): array {
        // Check if IP is blacklisted
        if ($ip && $this->ipBlockingService->isBlacklisted($ip)) {
            return $this->denied('IP is blacklisted');
        }

        // Check if IP is whitelisted
        if ($ip && $this->ipBlockingService->isWhitelisted($ip)) {
            return $this->allowed();
        }

        // Check if IP is blocked due to violations
        if ($ip && $this->ipBlockingService->isBlocked($ip)) {
            return $this->denied('IP is temporarily blocked');
        }

        // Get bot/suspicious flags
        $risk = $this->botDetectionService->getRiskLevel($identifier);

        // Get limit for this endpoint
        $limit = $this->getLimit($endpoint, $risk);

        if ($limit === 0) {
            return $this->denied('Rate limit exceeded');
        }

        // Get current request count
        $key = $this->getRedisKey($endpoint, $identifier);
        $current = (int) Cache::get($key, 0);

        // Increment counter
        $current++;
        Cache::put($key, $current, config('security-ratelimit.window'));

        // Check if exceeded
        if ($current > $limit) {
            $this->recordRateLimitHit($endpoint, $identifier, $risk, $ip, $userAgent);
            return $this->denied(
                'Rate limit exceeded',
                ['retry_after' => config('security-ratelimit.window')]
            );
        }

        return $this->allowed();
    }

    /**
     * Get rate limit for endpoint based on risk level.
     */
    private function getLimit(string $endpoint, string $riskLevel): int
    {
        $config = config('security-ratelimit.limits.' . $endpoint)
            ?? config('security-ratelimit.limits.default');

        return (int) ($config[$riskLevel] ?? $config['default'] ?? 60);
    }

    /**
     * Get Redis key for rate limiting.
     */
    private function getRedisKey(string $endpoint, string $identifier): string
    {
        return "rate_limit:{$endpoint}:{$identifier}";
    }

    /**
     * Record rate limit hit for monitoring.
     */
    private function recordRateLimitHit(
        string $endpoint,
        string $identifier,
        string $riskLevel,
        ?string $ip,
        ?string $userAgent
    ): void {
        if (!config('security-ratelimit.logging.log_rate_limit_hits')) {
            return;
        }

        Log::warning('Rate limit exceeded', [
            'endpoint' => $endpoint,
            'identifier' => $identifier,
            'risk_level' => $riskLevel,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Store event for analytics
        $eventKey = "event:rate_limit_hit:{$identifier}:" . now()->format('YmdHi');
        Cache::increment($eventKey, 1, 3600);
    }

    /**
     * Get request count for endpoint/identifier.
     */
    public function getRequestCount(string $endpoint, string $identifier): int
    {
        $key = $this->getRedisKey($endpoint, $identifier);
        return (int) Cache::get($key, 0);
    }

    /**
     * Get remaining requests for endpoint/identifier.
     */
    public function getRemainingRequests(string $endpoint, string $identifier): int
    {
        $risk = $this->botDetectionService->getRiskLevel($identifier);
        $limit = $this->getLimit($endpoint, $risk);
        $current = $this->getRequestCount($endpoint, $identifier);

        return max(0, $limit - $current);
    }

    /**
     * Reset counter for endpoint/identifier.
     */
    public function resetCounter(string $endpoint, string $identifier): void
    {
        $key = $this->getRedisKey($endpoint, $identifier);
        Cache::forget($key);
    }

    /**
     * Get analytics for endpoint.
     */
    public function getAnalytics(string $endpoint, int $minutes = 60): array
    {
        $stats = [];
        for ($i = 0; $i < $minutes; $i++) {
            $eventKey = "event:rate_limit_hit:*:" . now()->subMinutes($i)->format('YmdHi');
            // Note: This is simplified; in production, use Redis SCAN
            $stats[] = Cache::get($eventKey, 0);
        }

        return [
            'total_hits' => array_sum($stats),
            'period_minutes' => $minutes,
            'average_per_minute' => array_sum($stats) / max($minutes, 1),
        ];
    }

    private function allowed(): array
    {
        return ['allowed' => true];
    }

    private function denied(string $message, array $metadata = []): array
    {
        return array_merge([
            'allowed' => false,
            'message' => $message,
        ], $metadata);
    }
}
