<?php

namespace App\Services\GiftCard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class RateLockService
{
    public function __construct(private readonly RateEngineService $rateEngine)
    {
    }

    public function lockRates(string $brand, float $cardValue, string $userId, string $transactionType = 'sell'): array
    {
        $duration = (int) config('giftcard_arbitrage.lock_duration_seconds', 60);
        $rates = $this->rateEngine->getRates($brand, $cardValue);
        $lockId = (string) Str::uuid();

        $lockData = [
            'lock_id' => $lockId,
            'user_id' => $userId,
            'brand' => $rates['brand'],
            'brand_label' => $rates['brand_label'],
            'card_value' => $rates['card_value'],
            'transaction_type' => in_array($transactionType, ['buy', 'sell'], true) ? $transactionType : 'sell',
            'rates' => $rates,
            'locked_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($duration)->toISOString(),
            'seconds_remaining' => $duration,
        ];

        $this->cache()->put($this->lockKey($lockId), $lockData, $duration);
        Log::info('giftcard.rate.locked', [
            'lock_id' => $lockId,
            'user_id' => $userId,
            'brand' => $rates['brand'],
            'transaction_type' => $lockData['transaction_type'],
        ]);

        return $lockData;
    }

    public function getLockedRates(string $lockId): ?array
    {
        $lockData = $this->cache()->get($this->lockKey($lockId));

        if (! $lockData) {
            return null;
        }

        $secondsRemaining = now()->diffInSeconds($lockData['expires_at'], false);
        if ($secondsRemaining <= 0) {
            $this->cache()->forget($this->lockKey($lockId));
            Event::dispatch('rate.lock.expired', ['lock_id' => $lockId]);

            return null;
        }

        $lockData['seconds_remaining'] = (int) $secondsRemaining;

        return $lockData;
    }

    public function validateAndUseLock(string $lockId, string $userId, ?string $transactionType = null): array
    {
        $lockData = $this->getLockedRates($lockId);

        if (! $lockData) {
            throw new RuntimeException('Rate expired. Please refresh pricing before continuing.');
        }

        if ((string) $lockData['user_id'] !== (string) $userId) {
            throw new RuntimeException('Rate lock does not belong to this user.');
        }

        if ($transactionType && $lockData['transaction_type'] !== $transactionType) {
            throw new RuntimeException('Rate lock transaction type mismatch.');
        }

        $this->assertMarginIsStillSafe($lockData);
        $this->cache()->forget($this->lockKey($lockId));

        Log::info('giftcard.rate.lock.consumed', ['lock_id' => $lockId, 'user_id' => $userId]);

        return $lockData['rates'];
    }

    public function releaseLock(string $lockId): bool
    {
        $exists = $this->cache()->has($this->lockKey($lockId));
        $this->cache()->forget($this->lockKey($lockId));

        return $exists;
    }

    public function getLockStatus(string $lockId): array
    {
        $lockData = $this->getLockedRates($lockId);

        if (! $lockData) {
            return ['status' => 'expired', 'lock_id' => $lockId, 'seconds_remaining' => 0];
        }

        return [
            'status' => 'active',
            'lock_id' => $lockId,
            'seconds_remaining' => $lockData['seconds_remaining'],
            'expires_at' => $lockData['expires_at'],
            'brand' => $lockData['brand'],
            'card_value' => $lockData['card_value'],
            'transaction_type' => $lockData['transaction_type'],
        ];
    }

    private function assertMarginIsStillSafe(array $lockData): void
    {
        $rates = $lockData['rates'];
        if (($rates['sell_rate'] - $rates['buy_rate']) <= 0 || $rates['platform_profit'] <= 0) {
            throw new RuntimeException('Locked rate no longer satisfies margin policy.');
        }
    }

    private function lockKey(string $lockId): string
    {
        return "giftcard:rate-lock:{$lockId}";
    }

    private function cache()
    {
        return Cache::store(config('giftcard_arbitrage.cache_store'));
    }
}
