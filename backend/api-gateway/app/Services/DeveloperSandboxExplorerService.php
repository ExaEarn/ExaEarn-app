<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperProject;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class DeveloperSandboxExplorerService
{
    private const ALLOWED_PATHS = [
        'GET' => [
            '#^/api/developer/v1/(time|exchange-info|status|markets|tickers)$#',
            '#^/api/developer/v1/(ticker|orderbook|trades|klines)/[A-Za-z0-9_-]+$#',
            '#^/api/developer/v1/wallet/balances$#',
            '#^/api/developer/v1/spot/orders/[A-Za-z0-9-]+$#',
            '#^/api/developer/v1/(futures|margin|staking|copy|exaai)/[A-Za-z0-9_./-]+$#',
        ],
        'POST' => [
            '#^/api/developer/v1/spot/orders$#',
            '#^/api/developer/v1/realtime/session$#',
            '#^/api/developer/v1/(futures|margin|staking|copy|exaai|convert)/[A-Za-z0-9_./-]+$#',
        ],
        'PATCH' => [
            '#^/api/developer/v1/(staking|copy)/[A-Za-z0-9_./-]+$#',
        ],
        'DELETE' => [
            '#^/api/developer/v1/(futures|copy)/[A-Za-z0-9_./-]+$#',
        ],
    ];

    public function execute(DeveloperProject $project, DeveloperApiKey $key, array $payload, string $ip): array
    {
        $sandbox = $project->environments()->where('type', 'sandbox')->where('status', 'active')->exists();
        if (!$sandbox || $key->environment !== 'sandbox') {
            throw new RuntimeException('The documentation explorer is sandbox-only.');
        }
        if ($key->project_id !== $project->id || $key->status !== 'active') {
            throw new RuntimeException('Select an active API key owned by this sandbox project.');
        }
        if (! $key->encrypted_api_key) {
            throw new RuntimeException('This legacy key cannot be used by the explorer. Create a new sandbox explorer key.');
        }
        if ($key->passphrase_hash) {
            throw new RuntimeException('Explorer keys cannot use a passphrase because passphrases are not recoverable.');
        }

        $method = strtoupper((string) $payload['method']);
        $target = (string) $payload['path'];
        $parts = parse_url($target);
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');
        if (! str_starts_with($path, '/api/developer/v1/') || ! $this->allowed($method, $path)) {
            throw new RuntimeException('This endpoint is not available in the sandbox explorer.');
        }

        $body = in_array($method, ['POST', 'PATCH', 'PUT'], true)
            ? json_encode($payload['body'] ?? (object) [], JSON_THROW_ON_ERROR)
            : '';
        $timestamp = (string) time();
        $nonce = 'explorer_' . bin2hex(random_bytes(16));
        $canonical = $method . "\n" . $path . "\n" . $query . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
        $apiKey = Crypt::decryptString((string) $key->encrypted_api_key);
        $secret = Crypt::decryptString((string) $key->encrypted_secret);

        $request = Request::create($target, $method, [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_EXA_API_KEY' => $apiKey,
            'HTTP_EXA_API_TIMESTAMP' => $timestamp,
            'HTTP_EXA_API_NONCE' => $nonce,
            'HTTP_EXA_API_SIGNATURE' => hash_hmac('sha256', $canonical, $secret),
        ], $body);

        $started = microtime(true);
        $response = app(Kernel::class)->handle($request);
        $decoded = json_decode((string) $response->getContent(), true);

        return [
            'environment' => 'sandbox',
            'method' => $method,
            'path' => $target,
            'status' => $response->getStatusCode(),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'request_id' => $response->headers->get('X-Exa-Request-Id'),
            'headers' => array_filter([
                'content-type' => $response->headers->get('Content-Type'),
                'x-exa-request-id' => $response->headers->get('X-Exa-Request-Id'),
                'retry-after' => $response->headers->get('Retry-After'),
            ]),
            'body' => is_array($decoded) ? $decoded : ['message' => 'The sandbox endpoint returned a non-JSON response.'],
            'request_headers' => [
                'accept' => 'application/json',
                'content-type' => $body !== '' ? 'application/json' : null,
                'exa-api-key' => '[REDACTED]',
                'exa-api-signature' => '[REDACTED]',
            ],
        ];
    }

    private function allowed(string $method, string $path): bool
    {
        foreach (self::ALLOWED_PATHS[$method] ?? [] as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
