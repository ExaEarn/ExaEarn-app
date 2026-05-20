<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MarketStreamService
{
    public function publish(array $payload): void
    {
        $driver = (string) config('trading.stream.driver', 'redis');
        $channel = (string) config('trading.stream.channel', 'exaearn.market.stream');
        $fallbackToHttp = (bool) config('trading.stream.fallback_to_http', true);

        if ($driver === 'redis') {
            try {
                Redis::publish($channel, json_encode($payload, JSON_THROW_ON_ERROR));
                return;
            } catch (\Throwable $exception) {
                Log::warning('Failed to publish market stream payload to Redis.', [
                    'error' => $exception->getMessage(),
                    'channel' => $channel,
                    'payload_type' => $payload['type'] ?? null,
                    'pair' => $payload['pair'] ?? null,
                ]);

                if (!$fallbackToHttp) {
                    return;
                }
            }
        }

        $url = rtrim((string) config('wallet.node.url'), '/');
        $secret = (string) config('wallet.node.secret');

        if ($url === '' || $secret === '') {
            Log::warning('Market stream publish skipped: node service config missing.');
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders([
                    'X-Service-Secret' => $secret,
                    'Accept' => 'application/json',
                ])
                ->post("{$url}/streams/market/publish", $payload)
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('Failed to publish market stream payload.', [
                'error' => $exception->getMessage(),
                'payload_type' => $payload['type'] ?? null,
                'pair' => $payload['pair'] ?? null,
            ]);
        }
    }
}
