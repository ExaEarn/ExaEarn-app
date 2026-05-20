<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\BotDetectionService;
use App\Services\Security\IPBlockingService;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        private readonly IPBlockingService $ipBlockingService,
        private readonly BotDetectionService $botDetectionService,
        private readonly SecurityEventLogger $eventLogger,
    ) {
    }

    /**
     * GET /api/admin/security/dashboard
     * Get security dashboard analytics.
     */
    public function getDashboard(): JsonResponse
    {
        $this->authorize('viewAdmin');

        $analytics = $this->eventLogger->getAnalytics(24);

        return response()->json([
            'data' => $analytics,
        ]);
    }

    /**
     * GET /api/admin/security/events
     * Get recent security events.
     */
    public function getEvents(Request $request): JsonResponse
    {
        $this->authorize('viewAdmin');

        $payload = $request->validate([
            'type' => 'nullable|string',
            'severity' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $limit = (int) ($payload['limit'] ?? 50);

        if ($payload['type'] ?? null) {
            $events = $this->eventLogger->getEventsByType($payload['type'], $limit);
        } else {
            $events = \App\Models\SecurityEvent::latest()
                ->limit($limit)
                ->get()
                ->map->toArray()
                ->all();
        }

        return response()->json([
            'data' => $events,
        ]);
    }

    /**
     * GET /api/admin/security/ips/blocked
     * Get all currently blocked IPs.
     */
    public function getBlockedIPs(Request $request): JsonResponse
    {
        $this->authorize('viewAdmin');

        // In production, retrieve from Redis/cache
        $blockedIPs = [];

        return response()->json([
            'data' => $blockedIPs,
        ]);
    }

    /**
     * POST /api/admin/security/ips/block
     * Manually block an IP address.
     */
    public function blockIP(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $payload = $request->validate([
            'ip' => 'required|ip',
            'reason' => 'required|string|max:255',
            'duration_minutes' => 'nullable|integer|min:1|max:10080',
        ]);

        $duration = ((int) ($payload['duration_minutes'] ?? 15)) * 60;
        $this->ipBlockingService->blockIP(
            $payload['ip'],
            $payload['reason'],
            $duration
        );

        return response()->json([
            'message' => 'IP blocked successfully',
            'data' => [
                'ip' => $payload['ip'],
                'blocked_until' => now()->addSeconds($duration)->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/security/ips/unblock
     * Unblock an IP address.
     */
    public function unblockIP(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $payload = $request->validate([
            'ip' => 'required|ip',
        ]);

        $this->ipBlockingService->unblockIP($payload['ip']);

        return response()->json([
            'message' => 'IP unblocked successfully',
        ]);
    }

    /**
     * POST /api/admin/security/ips/whitelist
     * Add IP to whitelist.
     */
    public function whitelistIP(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $payload = $request->validate([
            'ip' => 'required|ip',
        ]);

        $this->ipBlockingService->whitelist($payload['ip']);

        return response()->json([
            'message' => 'IP whitelisted successfully',
        ]);
    }

    /**
     * POST /api/admin/security/ips/blacklist
     * Add IP to blacklist.
     */
    public function blacklistIP(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $payload = $request->validate([
            'ip' => 'required|ip',
        ]);

        $this->ipBlockingService->blacklist($payload['ip']);

        return response()->json([
            'message' => 'IP blacklisted successfully',
        ]);
    }

    /**
     * POST /api/admin/security/identifiers/unflag
     * Remove suspicious flag from identifier.
     */
    public function unflagIdentifier(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $payload = $request->validate([
            'identifier' => 'required|string',
        ]);

        $this->botDetectionService->removeFlag($payload['identifier']);

        return response()->json([
            'message' => 'Identifier unflagged successfully',
        ]);
    }

    /**
     * GET /api/admin/security/users/{userId}/events
     * Get security events for user.
     */
    public function getUserEvents(int $userId): JsonResponse
    {
        $this->authorize('viewAdmin');

        $events = $this->eventLogger->getUserEvents($userId);

        return response()->json([
            'data' => $events,
        ]);
    }

    /**
     * GET /api/admin/security/ips/{ip}/events
     * Get security events for IP.
     */
    public function getIPEvents(string $ip): JsonResponse
    {
        $this->authorize('viewAdmin');

        $events = $this->eventLogger->getIPEvents($ip);

        return response()->json([
            'data' => $events,
        ]);
    }

    /**
     * GET /api/admin/security/settings
     * Get security configuration.
     */
    public function getSettings(): JsonResponse
    {
        $this->authorize('admin');

        return response()->json([
            'data' => [
                'rate_limiting_enabled' => config('security-ratelimit.enabled'),
                'bot_detection_enabled' => config('security-ratelimit.bot_detection.enabled'),
                'ip_blocking_enabled' => config('security-ratelimit.ip_blocking.enabled'),
                'captcha_enabled' => config('security-ratelimit.captcha.enabled'),
                'captcha_provider' => config('security-ratelimit.captcha.provider'),
            ],
        ]);
    }

    /**
     * POST /api/admin/security/settings/update
     * Update security configuration.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $payload = $request->validate([
            'rate_limiting_enabled' => 'nullable|boolean',
            'bot_detection_enabled' => 'nullable|boolean',
            'ip_blocking_enabled' => 'nullable|boolean',
            'captcha_enabled' => 'nullable|boolean',
        ]);

        // In production, persist these to database/cache
        foreach ($payload as $key => $value) {
            config(["security-ratelimit.{$key}" => $value]);
        }

        return response()->json([
            'message' => 'Settings updated successfully',
        ]);
    }
}
