<?php

namespace Tests\Unit;

use App\Support\StagingSafety;
use RuntimeException;
use Tests\TestCase;

class StagingSafetyTest extends TestCase
{
    public function test_staging_requires_the_explicit_safety_lock(): void
    {
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', null);

        $this->expectException(RuntimeException::class);
        StagingSafety::validateStartup();
    }

    public function test_staging_requires_financial_services_and_workers_disabled(): void
    {
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', 'locked');
        putenv('FINANCIAL_SERVICES_ENABLED=true');
        $this->expectException(RuntimeException::class);
        try {
            StagingSafety::validateStartup();
        } finally {
            putenv('FINANCIAL_SERVICES_ENABLED');
        }
    }

    public function test_staging_requires_workers_disabled_at_startup(): void
    {
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', 'locked');
        putenv('FINANCIAL_SERVICES_ENABLED=false');
        putenv('STAGING_WORKERS_ENABLED=true');
        $this->expectException(RuntimeException::class);
        try {
            StagingSafety::validateStartup();
        } finally {
            putenv('FINANCIAL_SERVICES_ENABLED');
            putenv('STAGING_WORKERS_ENABLED');
        }
    }

    public function test_staging_rejects_queue_and_scheduler_workers(): void
    {
        config()->set('staging_safety.enabled', true);
        $this->expectException(RuntimeException::class);
        StagingSafety::assertWorkerAllowed(['artisan', 'queue:work']);
    }

    public function test_staging_requires_testnet_rpc_urls_and_empty_signing_secrets(): void
    {
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', 'locked');
        putenv('FINANCIAL_SERVICES_ENABLED=false');
        putenv('STAGING_WORKERS_ENABLED=false');
        putenv('CUSTODY_PRODUCTION_ENABLED=false');
        putenv('BASE_RPC_URL=https://mainnet.base.org');
        $this->expectException(RuntimeException::class);
        try {
            StagingSafety::validateStartup();
        } finally {
            putenv('FINANCIAL_SERVICES_ENABLED');
            putenv('STAGING_WORKERS_ENABLED');
            putenv('CUSTODY_PRODUCTION_ENABLED');
            putenv('BASE_RPC_URL');
        }
    }

    public function test_staging_rejects_missing_testnet_rpc_url(): void
    {
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', 'locked');
        putenv('FINANCIAL_SERVICES_ENABLED=false');
        putenv('STAGING_WORKERS_ENABLED=false');
        putenv('CUSTODY_PRODUCTION_ENABLED=false');
        putenv('BITCOIN_NETWORK=testnet');
        foreach (config('staging_safety.testnet_rpcs') as $name => $value) {
            if ($name !== 'BASE_RPC_URL') {
                putenv($name.'='.$value);
            }
        }

        $this->expectException(RuntimeException::class);
        try {
            StagingSafety::validateStartup();
        } finally {
            putenv('FINANCIAL_SERVICES_ENABLED');
            putenv('STAGING_WORKERS_ENABLED');
            putenv('CUSTODY_PRODUCTION_ENABLED');
            putenv('BITCOIN_NETWORK');
            foreach (array_keys(config('staging_safety.testnet_rpcs')) as $name) {
                putenv($name);
            }
        }
    }

    public function test_staging_rejects_populated_signing_secrets(): void
    {
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', 'locked');
        putenv('FINANCIAL_SERVICES_ENABLED=false');
        putenv('STAGING_WORKERS_ENABLED=false');
        putenv('CUSTODY_PRODUCTION_ENABLED=false');
        putenv('BITCOIN_NETWORK=testnet');
        foreach (config('staging_safety.testnet_rpcs') as $name => $value) {
            putenv($name.'='.$value);
        }
        foreach (config('staging_safety.required_values') as $name => $value) {
            putenv($name.'='.$value);
        }
        putenv('PRIVATE_KEY=fixture-only');
        $this->expectException(RuntimeException::class);
        try {
            StagingSafety::validateStartup();
        } finally {
            putenv('FINANCIAL_SERVICES_ENABLED');
            putenv('STAGING_WORKERS_ENABLED');
            putenv('CUSTODY_PRODUCTION_ENABLED');
            putenv('BITCOIN_NETWORK');
            putenv('PRIVATE_KEY');
            foreach (array_keys(config('staging_safety.testnet_rpcs')) as $name) {
                putenv($name);
            }
            foreach (array_keys(config('staging_safety.required_values')) as $name) {
                putenv($name);
            }
        }
    }

    public function test_staging_detection_fails_closed_when_deployment_marker_disagrees(): void
    {
        config()->set('app.env', 'staging');
        config()->set('staging_safety.deployment_env', 'production');
        $this->expectException(RuntimeException::class);
        StagingSafety::enabled();
    }

    public function test_staging_detection_rejects_a_mistyped_staging_marker(): void
    {
        config()->set('app.env', 'stagng');
        $this->expectException(RuntimeException::class);
        StagingSafety::enabled();
    }

    public function test_financial_operations_are_rejected_by_the_central_gate(): void
    {
        $this->expectException(RuntimeException::class);
        StagingSafety::denyFinancialOperation('test operation');
    }
}
