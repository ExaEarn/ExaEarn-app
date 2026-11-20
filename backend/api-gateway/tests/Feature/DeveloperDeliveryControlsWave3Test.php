<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class DeveloperDeliveryControlsWave3Test extends TestCase
{
    public function test_fail_closed_delivery_controls_are_versioned(): void
    {
        $root = realpath(base_path('../..'));
        $this->assertNotFalse($root);

        foreach ([
            '.github/workflows/developer-platform-gates.yml',
            '.gitleaks.toml',
            '.dockerignore',
            'backend/api-gateway/.dockerignore',
            'backend/api-gateway/Dockerfile',
            'backend/services/blockchain-service/Dockerfile',
            'infrastructure/developer-platform/kubernetes/base.yaml',
            'infrastructure/developer-platform/kubernetes/network-policy.yaml',
            'infrastructure/developer-platform/kubernetes/ingress.yaml',
            'scripts/validate-production-config.php',
            'scripts/classify-migrations.php',
            'scripts/postgres-restore-drill.ps1',
        ] as $path) {
            $this->assertFileExists($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        }

        $workflow = file_get_contents($root.'/.github/workflows/developer-platform-gates.yml');
        $this->assertStringContainsString('pnpm install --frozen-lockfile', $workflow);
        $this->assertStringContainsString('composer install --no-interaction --no-progress --prefer-dist', $workflow);
        $this->assertStringNotContainsString('composer install --no-interaction --no-progress --prefer-dist --locked', $workflow);
        $this->assertStringContainsString('image: postgres:16-alpine', $workflow);
        $this->assertStringContainsString('image: redis:7-alpine', $workflow);
        $this->assertStringContainsString('severity: \'CRITICAL,HIGH\'', $workflow);
        $this->assertStringContainsString('docker://ghcr.io/gitleaks/gitleaks:v8.30.1', $workflow);
        $this->assertStringContainsString('git --redact --verbose /github/workspace', $workflow);
        $this->assertStringContainsString('anchore/sbom-action', $workflow);

        $rootDockerignore = file_get_contents($root.'/.dockerignore');
        $apiDockerignore = file_get_contents($root.'/backend/api-gateway/.dockerignore');
        foreach (['**/.env', '**/.env.*', '**/*.pem', '**/*.key', '**/auth.json'] as $pattern) {
            $this->assertStringContainsString($pattern, $rootDockerignore);
        }
        foreach (['.env', '.env.*', '*.pem', '*.key', 'auth.json'] as $pattern) {
            $this->assertStringContainsString($pattern, $apiDockerignore);
        }
    }

    public function test_api_docker_image_installs_its_configured_redis_runtime(): void
    {
        $root = realpath(base_path('../..'));
        $dockerfile = file_get_contents($root.'/backend/api-gateway/Dockerfile');
        $databaseConfig = file_get_contents($root.'/backend/api-gateway/config/database.php');
        $envExample = file_get_contents($root.'/backend/api-gateway/.env.example');

        $this->assertStringContainsString('pecl install redis-6.3.0', $dockerfile);
        $this->assertStringContainsString('docker-php-ext-enable redis', $dockerfile);
        $this->assertStringContainsString("env('REDIS_CLIENT', 'phpredis')", $databaseConfig);
        $this->assertStringContainsString('REDIS_CLIENT=phpredis', $envExample);
    }

    public function test_production_manifests_fail_closed_at_sensitive_boundaries(): void
    {
        $root = realpath(base_path('../..'));
        $base = file_get_contents($root.'/infrastructure/developer-platform/kubernetes/base.yaml');
        $network = file_get_contents($root.'/infrastructure/developer-platform/kubernetes/network-policy.yaml');

        $this->assertStringContainsString('DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED: "false"', $base);
        $this->assertStringContainsString('TRUSTED_PROXIES: REQUIRED_AT_DEPLOYMENT', $base);
        $this->assertStringContainsString('name: webhook-worker', $base);
        $this->assertStringContainsString('name: default-deny', $network);
        $this->assertStringContainsString('name: webhook-worker-egress', $network);
        $this->assertStringContainsString('app: webhook-egress-proxy', $network);
        $this->assertStringNotContainsString('kind: Secret', $base);
        $this->assertStringNotContainsString('DB_PASSWORD:', $base);
    }
}
