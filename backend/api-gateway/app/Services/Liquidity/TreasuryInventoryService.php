<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\Account;
use App\Models\ExternalVenueBalance;
use App\Models\TreasuryLiquidityBucket;
use App\Models\WithdrawalLiquidityReserve;
use App\Services\FinancialDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TreasuryInventoryService
{
    public const BUCKETS = [
        'WITHDRAWAL_RESERVE',
        'CONVERT_INVENTORY',
        'SPOT_MARKET_MAKING',
        'EXTERNAL_ROUTING',
        'MARGIN_LENDING',
        'FUTURES_INSURANCE',
        'STAKING_OPERATIONS',
        'NETWORK_FEES',
        'CORPORATE_RESERVE',
    ];

    public function snapshot(?string $asset = null): array
    {
        $assets = $asset ? collect([strtoupper($asset)]) : $this->assets();

        return $assets->mapWithKeys(fn (string $asset): array => [$asset => $this->assetSnapshot($asset)])->all();
    }

    public function allocateBucket(string $asset, string $bucket, string $amount, array $metadata = []): TreasuryLiquidityBucket
    {
        $asset = strtoupper($asset);
        $bucket = strtoupper($bucket);
        if (! in_array($bucket, self::BUCKETS, true)) {
            throw new \RuntimeException('Unsupported treasury liquidity bucket.');
        }

        return TreasuryLiquidityBucket::query()->updateOrCreate(
            ['asset' => $asset, 'bucket' => $bucket],
            [
                'bucket_id' => (string) (TreasuryLiquidityBucket::query()->where('asset', $asset)->where('bucket', $bucket)->value('bucket_id') ?: Str::uuid()),
                'allocated_amount' => FinancialDecimal::normalize($amount),
                'status' => 'ACTIVE',
                'metadata' => $metadata,
            ]
        );
    }

    public function availableForBucket(string $asset, string $bucket): string
    {
        $row = TreasuryLiquidityBucket::query()->where('asset', strtoupper($asset))->where('bucket', strtoupper($bucket))->first();
        if (! $row) {
            return '0';
        }

        return FinancialDecimal::sub((string) $row->allocated_amount, (string) $row->reserved_amount);
    }

    private function assetSnapshot(string $asset): array
    {
        $treasuryBalance = (string) Account::query()
            ->whereNull('user_id')
            ->whereIn('account_type', ['treasury', 'system_treasury', 'insurance_fund'])
            ->where('asset', $asset)
            ->sum('balance');

        $external = (string) ExternalVenueBalance::query()->where('asset', $asset)->sum('available');
        $externalLocked = (string) ExternalVenueBalance::query()->where('asset', $asset)->sum('locked');
        $reserve = WithdrawalLiquidityReserve::query()->where('asset', $asset)->first();

        $buckets = TreasuryLiquidityBucket::query()
            ->where('asset', $asset)
            ->get()
            ->mapWithKeys(fn (TreasuryLiquidityBucket $bucket): array => [
                $bucket->bucket => [
                    'allocated' => (string) $bucket->allocated_amount,
                    'reserved' => (string) $bucket->reserved_amount,
                    'available' => FinancialDecimal::sub((string) $bucket->allocated_amount, (string) $bucket->reserved_amount),
                    'status' => $bucket->status,
                ],
            ])
            ->all();

        return [
            'asset' => $asset,
            'treasury_balance' => FinancialDecimal::normalize($treasuryBalance),
            'external_available' => FinancialDecimal::normalize($external),
            'external_locked' => FinancialDecimal::normalize($externalLocked),
            'withdrawal_reserve' => $reserve ? [
                'minimum' => (string) $reserve->minimum_reserve,
                'target' => (string) $reserve->target_reserve,
                'stress' => (string) $reserve->stress_reserve,
                'status' => $reserve->status,
            ] : null,
            'buckets' => $buckets,
            'updated_at' => now()->toISOString(),
        ];
    }

    private function assets(): Collection
    {
        return Account::query()
            ->whereNull('user_id')
            ->whereIn('account_type', ['treasury', 'system_treasury', 'insurance_fund'])
            ->pluck('asset')
            ->merge(ExternalVenueBalance::query()->pluck('asset'))
            ->unique()
            ->values();
    }
}
