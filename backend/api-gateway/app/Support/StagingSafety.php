<?php

namespace App\Support;

use RuntimeException;

final class StagingSafety
{
    public static function enabled(): bool
    {
        $appEnv = strtolower((string) config('app.env', env('APP_ENV', '')));
        $deploymentEnv = strtolower((string) config('staging_safety.deployment_env', env('APP_DEPLOYMENT_ENV', env('VERCEL_ENV', ''))));
        $lockSignal = trim((string) config('staging_safety.lock', env('EXAEARN_STAGING_SAFETY_LOCK', ''))) !== '';

        foreach ([$appEnv, $deploymentEnv] as $marker) {
            if ($marker !== '' && $marker !== 'staging' && preg_match('/stag|stg/', $marker)) {
                throw new RuntimeException('Staging environment marker is invalid. Refusing to start.');
            }
        }

        if (($appEnv === 'staging' && $deploymentEnv !== '' && $deploymentEnv !== 'staging')
            || ($deploymentEnv === 'staging' && $appEnv !== 'staging')
            || ($lockSignal && (($appEnv !== '' && $appEnv !== 'staging') || ($deploymentEnv !== '' && $deploymentEnv !== 'staging')))) {
            throw new RuntimeException('Staging environment detection is inconsistent. Refusing to start.');
        }

        return $appEnv === 'staging' || $deploymentEnv === 'staging' || $lockSignal || (bool) config('staging_safety.enabled', false);
    }

    public static function validateStartup(): void
    {
        if (! self::enabled()) {
            return;
        }

        if (config('staging_safety.lock') !== config('staging_safety.required_lock')) {
            throw new RuntimeException('Staging safety lock is not enabled. Refusing to start.');
        }

        if (! self::isExplicitlyFalse('FINANCIAL_SERVICES_ENABLED')) {
            throw new RuntimeException('Financial services must be explicitly disabled in staging. Refusing to start.');
        }
        if (! self::isExplicitlyFalse('STAGING_WORKERS_ENABLED')) {
            throw new RuntimeException('Financial and background workers must be explicitly disabled in staging. Refusing to start.');
        }
        if (! self::isExplicitlyFalse('CUSTODY_PRODUCTION_ENABLED')) {
            throw new RuntimeException('Production custody must be explicitly disabled in staging. Refusing to start.');
        }

        foreach (config('staging_safety.testnet_rpcs', []) as $name => $expected) {
            $actual = (string) env($name, '');
            if ($actual !== $expected || str_contains(strtolower($actual), 'mainnet')) {
                throw new RuntimeException("Unsafe staging RPC configuration for {$name}. Refusing to start.");
            }
        }

        foreach (config('staging_safety.required_values', []) as $name => $expected) {
            if (strtolower(trim((string) env($name, ''))) !== strtolower($expected)) {
                throw new RuntimeException("Unsafe staging value for {$name}. Refusing to start.");
            }
        }

        foreach (config('staging_safety.sensitive_variables', []) as $name) {
            if (trim((string) env($name, '')) !== '') {
                throw new RuntimeException("{$name} must be empty in staging. Refusing to start.");
            }
        }
    }

    public static function denyFinancialOperation(string $operation): never
    {
        throw new RuntimeException("{$operation} is disabled by the centralized staging safety policy.");
    }

    public static function assertWorkerAllowed(array $arguments): void
    {
        if (! self::enabled()) {
            return;
        }

        $command = strtolower(implode(' ', array_map('strval', $arguments)));
        if (preg_match('/(?:^|\s)(?:queue:work|queue:listen|schedule:work|schedule:run|horizon)(?:\s|$)/', $command)) {
            throw new RuntimeException('Workers are disabled in staging. Refusing to start.');
        }
    }

    private static function isExplicitlyFalse(string $name): bool
    {
        $value = env($name, null);

        return $value === false || (is_string($value) && strtolower(trim($value)) === 'false');
    }
}
