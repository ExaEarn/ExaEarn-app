<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Log;

class SecurityEventLogger
{
    /**
     * Log security event.
     */
    public function logEvent(
        string $eventType,
        string $severity = 'info',
        ?int $userId = null,
        ?string $ip = null,
        ?string $endpoint = null,
        array $metadata = []
    ): SecurityEvent {
        if (!config('security-ratelimit.logging.enabled')) {
            return new SecurityEvent();
        }

        $event = SecurityEvent::create([
            'event_type' => $eventType,
            'severity' => $severity,
            'user_id' => $userId,
            'ip_address' => $ip,
            'endpoint' => $endpoint,
            'metadata' => $metadata,
        ]);

        // Also log to application log
        Log::channel('security')->log(
            strtolower($severity),
            $eventType,
            array_merge(['event_id' => $event->id], $metadata)
        );

        return $event;
    }

    /**
     * Log rate limit hit.
     */
    public function logRateLimitHit(
        string $endpoint,
        string $identifier,
        string $riskLevel,
        ?string $ip,
        ?int $userId = null
    ): SecurityEvent {
        if (!config('security-ratelimit.logging.log_rate_limit_hits')) {
            return new SecurityEvent();
        }

        return $this->logEvent(
            'security.rate_limit_hit',
            'warning',
            $userId,
            $ip,
            $endpoint,
            [
                'identifier' => $identifier,
                'risk_level' => $riskLevel,
            ]
        );
    }

    /**
     * Log bot detection.
     */
    public function logBotDetection(
        string $identifier,
        array $factors,
        string $riskLevel,
        ?string $ip,
        ?int $userId = null
    ): SecurityEvent {
        if (!config('security-ratelimit.logging.log_bot_detections')) {
            return new SecurityEvent();
        }

        return $this->logEvent(
            'security.bot_detected',
            'warning',
            $userId,
            $ip,
            null,
            [
                'identifier' => $identifier,
                'risk_factors' => $factors,
                'risk_level' => $riskLevel,
            ]
        );
    }

    /**
     * Log IP block.
     */
    public function logIPBlock(string $ip, string $reason, int $durationSeconds): SecurityEvent
    {
        if (!config('security-ratelimit.logging.log_ip_blocks')) {
            return new SecurityEvent();
        }

        return $this->logEvent(
            'security.ip_blocked',
            'warning',
            null,
            $ip,
            null,
            [
                'reason' => $reason,
                'duration_seconds' => $durationSeconds,
            ]
        );
    }

    /**
     * Log failed login.
     */
    public function logFailedLogin(int $userId, string $ip, string $reason): SecurityEvent
    {
        return $this->logEvent(
            'security.failed_login',
            'info',
            $userId,
            $ip,
            '/login',
            ['reason' => $reason]
        );
    }

    /**
     * Log successful sensitive action.
     */
    public function logSensitiveAction(
        int $userId,
        string $action,
        ?string $ip = null,
        array $metadata = []
    ): SecurityEvent {
        return $this->logEvent(
            "security.sensitive_action.{$action}",
            'info',
            $userId,
            $ip,
            null,
            $metadata
        );
    }

    /**
     * Get recent events for user.
     */
    public function getUserEvents(int $userId, int $limit = 50): array
    {
        return SecurityEvent::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($e) => $e->toArray())
            ->all();
    }

    /**
     * Get recent events for IP.
     */
    public function getIPEvents(string $ip, int $limit = 50): array
    {
        return SecurityEvent::where('ip_address', $ip)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($e) => $e->toArray())
            ->all();
    }

    /**
     * Get events by type.
     */
    public function getEventsByType(string $eventType, int $limit = 50): array
    {
        return SecurityEvent::where('event_type', $eventType)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($e) => $e->toArray())
            ->all();
    }

    /**
     * Get security analytics.
     */
    public function getAnalytics(int $hoursAgo = 24): array
    {
        $from = now()->subHours($hoursAgo);

        $events = SecurityEvent::where('created_at', '>=', $from)->get();

        return [
            'total_events' => $events->count(),
            'by_type' => $events->groupBy('event_type')->map->count(),
            'by_severity' => $events->groupBy('severity')->map->count(),
            'unique_ips' => $events->pluck('ip_address')->filter()->unique()->count(),
            'unique_users' => $events->pluck('user_id')->filter()->unique()->count(),
        ];
    }
}
