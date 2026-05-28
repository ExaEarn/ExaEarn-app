<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RealtimeStreamService
{
    public function publishMarketPriceUpdate(array $marketData): void
    {
        $payload = [
            'event' => 'price:update',
            'timestamp' => now()->toIso8601String(),
            'data' => $marketData,
        ];

        $this->publish($this->getPriceChannel(), $payload);
    }

    public function publishPayload(string $channel, array $payload): void
    {
        $this->publish($channel, $payload);
    }

    public function publish(string $channel, array $payload): void
    {
        if ($this->shouldUseRedis()) {
            if ($this->publishToRedis($channel, $payload)) {
                return;
            }
        }

        $this->publishToNode($channel, $payload);
    }

    private function shouldUseRedis(): bool
    {
        return config('streaming.driver', 'redis') === 'redis';
    }

    private function publishToRedis(string $channel, array $payload): bool
    {
        try {
            Redis::publish($channel, json_encode($payload, JSON_THROW_ON_ERROR));
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Realtime stream publish failed via Redis', [
                'error' => $exception->getMessage(),
                'channel' => $channel,
            ]);

            return false;
        }
    }

    private function publishToNode(string $channel, array $payload): void
    {
        $url = rtrim((string) config('streaming.node.url', 'http://localhost:4000'), '/');
        $secret = (string) config('streaming.node.secret');
        $timeout = (int) config('streaming.node.timeout_seconds', 15);

        if ($url === '') {
            Log::warning('Realtime stream publish skipped: node URL is not configured.');
            return;
        }

        $endpoint = match ($channel) {
            $this->getPriceChannel() => 'price',
            $this->getPortfolioChannel() => 'portfolio',
            default => 'market',
        };

        try {
            Http::timeout($timeout)
                ->withHeaders([
                    'X-Service-Secret' => $secret,
                    'Accept' => 'application/json',
                ])
                ->post("{$url}/streams/{$endpoint}/publish", $payload)
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('Realtime stream publish failed via HTTP fallback', [
                'error' => $exception->getMessage(),
                'endpoint' => "{$url}/streams/{$endpoint}/publish",
                'channel' => $channel,
            ]);
        }
    }

    private function getPriceChannel(): string
    {
        return config('streaming.price_channel', 'price_updates');
    }

    private function getPortfolioChannel(): string
    {
        return config('streaming.portfolio_channel', 'portfolio_updates');
    }
}
