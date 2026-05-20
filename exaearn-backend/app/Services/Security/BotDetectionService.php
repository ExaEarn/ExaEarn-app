<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotDetectionService
{
    /**
     * Analyze request and determine risk level.
     */
    public function analyzeRequest(
        string $identifier,
        string $endpoint,
        ?string $userAgent,
        ?string $ip,
        ?int $userId = null,
        array $metadata = []
    ): array {
        $riskFactors = [];

        // Check for suspicious user agent
        if ($userAgent && $this->hasSuspiciousUserAgent($userAgent)) {
            $riskFactors[] = 'suspicious_user_agent';
        }

        // Check for rapid requests
        if ($this->detectRapidRequests($identifier)) {
            $riskFactors[] = 'rapid_requests';
        }

        // Check for repeated failures
        if ($userId && $this->detectRepeatedFailures($userId)) {
            $riskFactors[] = 'repeated_failures';
        }

        // Check for missing frontend signals
        if (!($metadata['has_frontend_signals'] ?? false)) {
            $riskFactors[] = 'missing_frontend_signals';
        }

        // Calculate risk level
        $riskLevel = $this->calculateRiskLevel(count($riskFactors));

        // Flag if necessary
        if ($riskLevel !== 'default') {
            $this->flagIdentifier($identifier, $riskLevel);
        }

        return [
            'risk_level' => $riskLevel,
            'factors' => $riskFactors,
            'is_bot' => $riskLevel === 'blocked',
            'requires_captcha' => $riskLevel === 'suspicious' || $riskLevel === 'blocked',
        ];
    }

    /**
     * Check for suspicious user agent patterns.
     */
    private function hasSuspiciousUserAgent(string $userAgent): bool
    {
        $blockedPatterns = config('security-ratelimit.blocked_user_agents', []);
        $userAgentLower = strtolower($userAgent);

        foreach ($blockedPatterns as $pattern) {
            if (stripos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect rapid sequential requests.
     */
    private function detectRapidRequests(string $identifier): bool
    {
        $key = "requests:{$identifier}:" . now()->format('Ys'); // Second precision
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, 2);

        $threshold = config('security-ratelimit.bot_detection.frequency_threshold', 10);
        return $count + 1 > $threshold;
    }

    /**
     * Detect repeated failed attempts (from failed login logs).
     */
    private function detectRepeatedFailures(int $userId): bool
    {
        $key = "failed_attempts:{$userId}";
        $count = (int) Cache::get($key, 0);

        return $count >= config('security-ratelimit.bot_detection.failed_attempts_threshold', 5);
    }

    /**
     * Calculate risk level based on number of risk factors.
     */
    private function calculateRiskLevel(int $factorCount): string
    {
        if ($factorCount >= 4) {
            return 'blocked';
        }
        if ($factorCount >= 2) {
            return 'suspicious';
        }
        return 'default';
    }

    /**
     * Get current risk level for identifier.
     */
    public function getRiskLevel(string $identifier): string
    {
        $flag = Cache::get("bot_flag:{$identifier}");
        return $flag ?? 'default';
    }

    /**
     * Flag identifier as suspicious or blocked.
     */
    private function flagIdentifier(string $identifier, string $level): void
    {
        $duration = config('security-ratelimit.bot_detection.block_duration', 900);
        Cache::put("bot_flag:{$identifier}", $level, $duration);

        Log::warning('Bot detected', [
            'identifier' => $identifier,
            'risk_level' => $level,
            'block_duration' => $duration,
        ]);
    }

    /**
     * Remove flag from identifier.
     */
    public function removeFlag(string $identifier): void
    {
        Cache::forget("bot_flag:{$identifier}");
    }

    /**
     * Check if identifier is flagged as bot.
     */
    public function isFlaggedAsBot(string $identifier): bool
    {
        return (bool) Cache::get("bot_flag:{$identifier}");
    }

    /**
     * Record failed login attempt.
     */
    public function recordFailedAttempt(int $userId): void
    {
        $key = "failed_attempts:{$userId}";
        $window = config('security-ratelimit.bot_detection.failed_attempts_window', 300);
        
        Cache::increment($key, 1, $window);
    }

    /**
     * Reset failed attempts for user.
     */
    public function resetFailedAttempts(int $userId): void
    {
        Cache::forget("failed_attempts:{$userId}");
    }

    /**
     * Get current failed attempt count.
     */
    public function getFailedAttempts(int $userId): int
    {
        return (int) Cache::get("failed_attempts:{$userId}", 0);
    }

    /**
     * Get all flagged identifiers (for admin).
     */
    public function getFlaggedIdentifiers(int $limit = 100): array
    {
        // In production, use Redis SCAN for efficiency
        // This is a simplified version
        return [];
    }
}
