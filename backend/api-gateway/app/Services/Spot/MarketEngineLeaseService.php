<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\SpotMarketEngineLease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MarketEngineLeaseService
{
    public function instanceId(): string
    {
        return (string) config('trading.engine.instance_id', gethostname() ?: 'exaearn-spot-engine');
    }

    public function acquire(Market $market, ?string $ownerInstanceId = null): SpotMarketEngineLease
    {
        $owner = $ownerInstanceId ?: $this->instanceId();
        $ttl = max(5, (int) config('trading.engine.lease_ttl_seconds', 30));

        return DB::transaction(function () use ($market, $owner, $ttl): SpotMarketEngineLease {
            $now = now();
            $lease = SpotMarketEngineLease::query()
                ->where('market_id', $market->id)
                ->lockForUpdate()
                ->first();

            if (!$lease) {
                return SpotMarketEngineLease::query()->create([
                    'market_id' => $market->id,
                    'market_symbol' => $market->symbol,
                    'owner_instance_id' => $owner,
                    'lease_token' => (string) Str::uuid(),
                    'generation' => 1,
                    'acquired_at' => $now,
                    'heartbeat_at' => $now,
                    'expires_at' => $now->copy()->addSeconds($ttl),
                    'status' => 'active',
                    'metadata' => ['source' => 'phase2b'],
                ]);
            }

            $isCurrentOwner = $lease->owner_instance_id === $owner && $lease->status === 'active';
            $isExpired = $lease->expires_at === null || $lease->expires_at->lte($now) || $lease->status !== 'active';

            if (!$isCurrentOwner && !$isExpired) {
                throw new RuntimeException('Market is owned by another active engine instance.');
            }

            $lease->owner_instance_id = $owner;
            $lease->market_symbol = $market->symbol;
            $lease->lease_token = $isCurrentOwner ? $lease->lease_token : (string) Str::uuid();
            $lease->generation = $isCurrentOwner ? (int) $lease->generation : ((int) $lease->generation) + 1;
            $lease->heartbeat_at = $now;
            $lease->expires_at = $now->copy()->addSeconds($ttl);
            $lease->status = 'active';
            $lease->save();

            return $lease->fresh();
        });
    }

    public function heartbeat(Market $market, string $leaseToken, int $generation, ?string $ownerInstanceId = null): SpotMarketEngineLease
    {
        $owner = $ownerInstanceId ?: $this->instanceId();
        $ttl = max(5, (int) config('trading.engine.lease_ttl_seconds', 30));

        return DB::transaction(function () use ($generation, $leaseToken, $market, $owner, $ttl): SpotMarketEngineLease {
            $lease = SpotMarketEngineLease::query()->where('market_id', $market->id)->lockForUpdate()->firstOrFail();
            $this->assertFencing($lease, $leaseToken, $generation, $owner);
            $lease->heartbeat_at = now();
            $lease->expires_at = now()->addSeconds($ttl);
            $lease->save();

            return $lease->fresh();
        });
    }

    public function assertCurrent(Market $market, string $leaseToken, int $generation, ?string $ownerInstanceId = null): SpotMarketEngineLease
    {
        $owner = $ownerInstanceId ?: $this->instanceId();
        $lease = SpotMarketEngineLease::query()->where('market_id', $market->id)->firstOrFail();
        $this->assertFencing($lease, $leaseToken, $generation, $owner);

        return $lease;
    }

    public function expire(Market $market): void
    {
        SpotMarketEngineLease::query()
            ->where('market_id', $market->id)
            ->update(['status' => 'expired', 'expires_at' => now()]);
    }

    private function assertFencing(SpotMarketEngineLease $lease, string $leaseToken, int $generation, string $owner): void
    {
        if ($lease->status !== 'active' || $lease->expires_at === null || $lease->expires_at->lte(now())) {
            throw new RuntimeException('Market engine lease is not active.');
        }

        if ($lease->owner_instance_id !== $owner || $lease->lease_token !== $leaseToken || (int) $lease->generation !== $generation) {
            throw new RuntimeException('Stale market engine fencing token rejected.');
        }
    }
}
