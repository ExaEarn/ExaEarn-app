<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IPBlockingService
{
    /**
     * Check if IP is whitelisted.
     */
    public function isWhitelisted(string $ip): bool
    {
        $whitelist = config('security-ratelimit.whitelist.ips', []);
        return in_array($ip, $whitelist, true);
    }

    /**
     * Check if IP is blacklisted.
     */
    public function isBlacklisted(string $ip): bool
    {
        $blacklist = config('security-ratelimit.blacklist.ips', []);
        return in_array($ip, $blacklist, true);
    }

    /**
     * Check if IP is currently blocked.
     */
    public function isBlocked(string $ip): bool
    {
        return (bool) Cache::get("blocked_ip:{$ip}");
    }

    /**
     * Block an IP address.
     */
    public function blockIP(string $ip, string $reason = 'violation', int $durationSeconds = 900): void
    {
        Cache::put("blocked_ip:{$ip}", [
            'reason' => $reason,
            'blocked_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($durationSeconds)->toIso8601String(),
        ], $durationSeconds);

        Log::warning('IP blocked', [
            'ip' => $ip,
            'reason' => $reason,
            'duration_seconds' => $durationSeconds,
        ]);
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIP(string $ip): void
    {
        Cache::forget("blocked_ip:{$ip}");
        Log::info('IP unblocked', ['ip' => $ip]);
    }

    /**
     * Get block info for IP.
     */
    public function getBlockInfo(string $ip): ?array
    {
        return Cache::get("blocked_ip:{$ip}");
    }

    /**
     * Record failed login for IP.
     */
    public function recordFailedLogin(string $ip): void
    {
        $key = "failed_logins:{$ip}";
        $window = 300; // 5 minutes

        Cache::increment($key, 1, $window);

        $count = (int) Cache::get($key, 0);
        $threshold = config('security-ratelimit.ip_blocking.failed_login_threshold', 10);

        if ($count >= $threshold) {
            $duration = config('security-ratelimit.ip_blocking.block_duration', 900);
            $this->blockIP($ip, 'failed_logins_threshold', $duration);
        }
    }

    /**
     * Reset failed logins for IP.
     */
    public function resetFailedLogins(string $ip): void
    {
        Cache::forget("failed_logins:{$ip}");
    }

    /**
     * Get failed login count for IP.
     */
    public function getFailedLoginCount(string $ip): int
    {
        return (int) Cache::get("failed_logins:{$ip}", 0);
    }

    /**
     * Add IP to whitelist.
     */
    public function whitelist(string $ip): void
    {
        $whitelist = config('security-ratelimit.whitelist.ips', []);
        if (!in_array($ip, $whitelist, true)) {
            $whitelist[] = $ip;
            config(['security-ratelimit.whitelist.ips' => $whitelist]);
        }

        Log::info('IP whitelisted', ['ip' => $ip]);
    }

    /**
     * Add IP to blacklist.
     */
    public function blacklist(string $ip): void
    {
        $blacklist = config('security-ratelimit.blacklist.ips', []);
        if (!in_array($ip, $blacklist, true)) {
            $blacklist[] = $ip;
            config(['security-ratelimit.blacklist.ips' => $blacklist]);
        }

        Log::info('IP blacklisted', ['ip' => $ip]);
    }
}
