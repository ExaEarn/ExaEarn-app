<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceFingerprintService
{
    /**
     * Generate device fingerprint from request data.
     */
    public function generateFingerprint(array $data): string
    {
        $fingerprint = [
            'user_agent' => $data['user_agent'] ?? '',
            'ip_address' => $data['ip_address'] ?? '',
            'accept_language' => $data['accept_language'] ?? '',
            'accept_encoding' => $data['accept_encoding'] ?? '',
            'timezone_offset' => $data['timezone_offset'] ?? 'unknown',
            'screen_resolution' => $data['screen_resolution'] ?? 'unknown',
        ];

        return hash('sha256', json_encode($fingerprint));
    }

    /**
     * Register device for user.
     */
    public function registerDevice(int $userId, string $fingerprint, string $deviceName = 'Unknown Device'): void
    {
        $key = "user_devices:{$userId}:{$fingerprint}";
        Cache::put($key, [
            'name' => $deviceName,
            'first_seen' => now()->toIso8601String(),
            'last_seen' => now()->toIso8601String(),
        ], 86400 * 365); // 1 year
    }

    /**
     * Check if device is anomalous.
     */
    public function isAnomalousDevice(int $userId, string $fingerprint, string $ip): bool
    {
        if (!config('security-ratelimit.device_tracking.enabled')) {
            return false;
        }

        // Check if device is known for this user
        $key = "user_devices:{$userId}:{$fingerprint}";
        if (!Cache::has($key)) {
            return true; // New device
        }

        // Check if IP is different from usual
        return $this->hasUnusualIP($userId, $ip);
    }

    /**
     * Check for multiple accounts from single device.
     */
    public function checkMultipleAccounts(string $fingerprint): array
    {
        $key = "device_users:{$fingerprint}";
        $users = Cache::get($key, []);

        $maxAccounts = config('security-ratelimit.device_tracking.max_accounts_per_device', 3);
        
        return [
            'account_count' => count($users),
            'is_suspicious' => count($users) > $maxAccounts,
            'accounts' => $users,
        ];
    }

    /**
     * Link device to user.
     */
    public function linkDeviceToUser(int $userId, string $fingerprint): void
    {
        $key = "device_users:{$fingerprint}";
        $users = Cache::get($key, []);

        if (!in_array($userId, $users)) {
            $users[] = $userId;
            Cache::put($key, $users, 86400 * 365);
        }
    }

    /**
     * Check if IP is unusual for user.
     */
    private function hasUnusualIP(int $userId, string $ip): bool
    {
        $key = "user_ips:{$userId}";
        $ips = Cache::get($key, []);

        if (empty($ips)) {
            return false; // First login, not unusual
        }

        return !in_array($ip, $ips);
    }

    /**
     * Record IP for user.
     */
    public function recordIP(int $userId, string $ip): void
    {
        $key = "user_ips:{$userId}";
        $ips = Cache::get($key, []);

        if (!in_array($ip, $ips)) {
            $ips[] = $ip;
            // Keep only last 10 IPs
            if (count($ips) > 10) {
                array_shift($ips);
            }
            Cache::put($key, $ips, 86400 * 365);
        }
    }

    /**
     * Get devices for user.
     */
    public function getUserDevices(int $userId): array
    {
        $devices = [];
        // In production, iterate through Redis keys or database
        return $devices;
    }

    /**
     * Revoke device access.
     */
    public function revokeDevice(int $userId, string $fingerprint): void
    {
        $key = "user_devices:{$userId}:{$fingerprint}";
        Cache::forget($key);

        Log::info('Device revoked', ['user_id' => $userId, 'fingerprint' => $fingerprint]);
    }
}
