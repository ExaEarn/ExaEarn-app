<?php

declare(strict_types=1);

$script = dirname(__DIR__).'/validate-production-config.php';
$base = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.str_repeat('A', 44),
    'APP_URL' => 'https://api.exaearn.com',
    'DB_HOST' => 'postgres.internal.exaearn.com',
    'DB_DATABASE' => 'exaearn_production',
    'DB_USERNAME' => 'exaearn_runtime',
    'DB_PASSWORD' => str_repeat('d', 32),
    'REDIS_HOST' => 'redis.internal.exaearn.com',
    'NODE_SERVICE_SECRET' => str_repeat('n', 32),
    'TRUSTED_PROXIES' => '10.20.0.0/16',
    'DEVELOPER_API_RUNTIME_ENVIRONMENT' => 'production',
    'SECURITY_API_SIGNATURE_REQUIRED' => 'true',
    'DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED' => 'false',
    'DEVELOPER_PRODUCTION_WEBHOOK_EGRESS_VERIFIED' => 'false',
    'CORS_ALLOWED_ORIGINS' => 'https://developers.exaearn.com',
];

$run = static function (array $environment) use ($script): int {
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2), array_merge($_ENV, $environment));
    if (! is_resource($process)) return 255;
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($process);
};

if ($run($base) !== 0) exit(1);
foreach ([
    ['APP_URL' => 'http://localhost:8000'],
    ['DB_HOST' => 'db.example.invalid'],
    ['REDIS_HOST' => '127.0.0.1'],
    ['TRUSTED_PROXIES' => '*'],
    ['DB_PASSWORD' => 'short'],
    ['CORS_ALLOWED_ORIGINS' => 'https://developers.exaearn.com,http://localhost:5173'],
    ['DEVELOPER_PRODUCTION_WEBHOOK_DELIVERY_ENABLED' => 'true'],
] as $override) {
    if ($run(array_merge($base, $override)) === 0) exit(1);
}

echo "Production config validator fixtures: PASS\n";
