<?php

declare(strict_types=1);

namespace App\Services;

class ProductionConfigValidationService
{
    public function validate(?string $environment = null): array
    {
        $environment = $environment ? strtolower($environment) : strtolower(app()->environment());
        $connection = (string) config('database.default');
        $issues = [];

        if (in_array($environment, ['production', 'prod'], true) && $connection === 'sqlite') {
            $issues[] = 'SQLITE_PRODUCTION_FORBIDDEN';
        }
        if (in_array($environment, ['production', 'prod'], true) && config('queue.default') === 'sync') {
            $issues[] = 'SYNC_QUEUE_PRODUCTION_FORBIDDEN';
        }
        if (in_array($environment, ['production', 'prod'], true) && empty(config('app.key'))) {
            $issues[] = 'APP_KEY_REQUIRED';
        }

        return [
            'status' => $issues === [] ? 'PASS' : 'FAIL',
            'environment' => strtoupper($environment),
            'database_connection' => $connection,
            'queue_connection' => config('queue.default'),
            'issues' => $issues,
        ];
    }
}
