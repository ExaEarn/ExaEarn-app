<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

/**
 * Offline stored-value functionality is simulation-only. Require the configured
 * environment and Laravel's runtime environment to agree, and never allow
 * production even if the allow-list is accidentally broadened.
 */
final class OfflineEnvironmentGuard
{
    public static function allowsSimulation(): bool
    {
        $configured = strtolower(trim((string) config('app.env', '')));
        $allowed = array_map('strtolower', (array) config('exapay_offline.allowed_environments', ['testing', 'staging']));

        return in_array($configured, ['testing', 'staging'], true)
            && in_array($configured, $allowed, true)
            && app()->environment($configured)
            && !app()->environment('production');
    }
}
