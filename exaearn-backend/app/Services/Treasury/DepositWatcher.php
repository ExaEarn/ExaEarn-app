<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * DepositWatcher Service
 *
 * Manages blockchain deposit watching via the Node.js blockchain watcher service.
 * Coordinates with the external service to monitor treasury wallet addresses for incoming deposits.
 */
class DepositWatcher
{
    /**
     * Start watching a treasury wallet address for deposits.
     *
     * @param string $address Wallet address to watch
     * @param string $chain Blockchain chain name
     * @return bool Success status
     */
    public function watchAddress(string $address, string $chain): bool
    {
        $url = config('services.node.service_url');
        $secret = config('services.node.webhook_secret');

        if ($url === null || $url === '') {
            throw new RuntimeException('Node service URL is not configured. Set SERVICES_NODE_URL in .env');
        }

        try {
            $response = Http::withToken($secret)
                ->timeout(30)
                ->post("{$url}/watch", [
                    'address' => $address,
                    'chain' => $chain,
                    'webhook_url' => route('webhooks.treasury-deposits'),
                ]);

            if ($response->successful()) {
                Log::info('Deposit watcher started', [
                    'address' => $address,
                    'chain' => $chain,
                ]);
                return true;
            }

            Log::error('Deposit watcher start failed', [
                'address' => $address,
                'chain' => $chain,
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Deposit watcher start error', [
                'address' => $address,
                'chain' => $chain,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Stop watching a treasury wallet address.
     *
     * @param string $address Wallet address
     * @param string $chain Blockchain chain name
     * @return bool Success status
     */
    public function stopWatching(string $address, string $chain): bool
    {
        $url = config('services.node.service_url');
        $secret = config('services.node.webhook_secret');

        if ($url === null || $url === '') {
            throw new RuntimeException('Node service URL is not configured.');
        }

        try {
            $response = Http::withToken($secret)
                ->timeout(30)
                ->post("{$url}/unwatch", [
                    'address' => $address,
                    'chain' => $chain,
                ]);

            if ($response->successful()) {
                Log::info('Deposit watcher stopped', [
                    'address' => $address,
                    'chain' => $chain,
                ]);
                return true;
            }

            Log::error('Deposit watcher stop failed', [
                'address' => $address,
                'chain' => $chain,
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Deposit watcher stop error', [
                'address' => $address,
                'chain' => $chain,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get watcher status from Node service.
     *
     * @param string $chain Blockchain chain name
     * @return array Array of watched addresses
     */
    public function getWatcherStatus(string $chain): array
    {
        $url = config('services.node.service_url');
        $secret = config('services.node.webhook_secret');

        if ($url === null || $url === '') {
            throw new RuntimeException('Node service URL is not configured.');
        }

        try {
            $response = Http::withToken($secret)
                ->timeout(30)
                ->get("{$url}/watchers/{$chain}");

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get watcher status', [
                'chain' => $chain,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
