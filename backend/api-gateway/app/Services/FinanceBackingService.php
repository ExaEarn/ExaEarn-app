<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\FinanceAssetSource;
use App\Models\FinanceBackingSnapshot;
use App\Models\FinanceReconciliationBreak;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FinanceBackingService
{
    public function liabilitiesByAsset(): array
    {
        $rows = Account::query()
            ->where(function ($query): void {
                $query->where('owner_type', 'user')->orWhereNotNull('user_id');
            })
            ->where('status', 'active')
            ->selectRaw('asset, SUM(balance) as total')
            ->groupBy('asset')
            ->get();

        return $rows->mapWithKeys(function ($row): array {
            $total = FinancialDecimal::normalize((string) $row->total);
            return [strtoupper((string) $row->asset) => FinancialDecimal::compare($total, '0') > 0 ? $total : '0'];
        })->all();
    }

    public function assetSourcesByAsset(): Collection
    {
        return FinanceAssetSource::query()->where('status', 'ACTIVE')->get()->groupBy('asset');
    }

    public function calculate(?string $asset = null): array
    {
        $liabilities = $this->liabilitiesByAsset();
        $sources = $this->assetSourcesByAsset();
        $assets = $asset ? [strtoupper($asset)] : collect(array_merge(array_keys($liabilities), $sources->keys()->all()))->unique()->values()->all();
        $results = [];

        foreach ($assets as $assetCode) {
            $liability = $liabilities[$assetCode] ?? '0';
            $assetRows = $sources->get($assetCode, collect());
            $gross = '0';
            $restricted = '0';
            $eligible = '0';
            $freshness = 'FRESH';
            foreach ($assetRows as $row) {
                $amount = (string) $row->amount;
                $gross = FinancialDecimal::add($gross, $amount);
                if ($row->restricted) {
                    $restricted = FinancialDecimal::add($restricted, $amount);
                }
                if ($row->eligible_for_backing && ! $row->restricted && $row->freshness === 'FRESH') {
                    $eligible = FinancialDecimal::add($eligible, $amount);
                }
                if (in_array($row->freshness, ['STALE', 'UNAVAILABLE', 'UNVERIFIED'], true)) {
                    $freshness = $row->freshness;
                }
            }
            $surplus = FinancialDecimal::sub($eligible, $liability);
            $ratio = FinancialDecimal::compare($liability, '0') === 0 ? null : FinancialDecimal::div($eligible, $liability);
            $status = $this->status($ratio, $liability);

            $snapshot = FinanceBackingSnapshot::query()->create([
                'snapshot_uuid' => (string) Str::uuid(),
                'asset' => $assetCode,
                'liability' => $liability,
                'gross_assets' => $gross,
                'restricted_assets' => $restricted,
                'eligible_backing' => $eligible,
                'surplus_deficit' => $surplus,
                'coverage_ratio' => $ratio,
                'status' => $status,
                'freshness' => $freshness,
                'calculated_at' => now(),
                'metadata' => ['source_count' => $assetRows->count()],
            ]);

            if (in_array($status, ['HIGH_RISK', 'CRITICAL', 'UNKNOWN'], true) && FinancialDecimal::compare($liability, '0') > 0) {
                FinanceReconciliationBreak::query()->firstOrCreate([
                    'scope' => 'backing',
                    'code' => 'BACKING_'.$status,
                    'subject_reference' => $assetCode,
                    'status' => 'OPEN',
                ], [
                    'break_uuid' => (string) Str::uuid(),
                    'severity' => $status === 'CRITICAL' ? 'CRITICAL' : 'HIGH',
                    'subject_type' => 'asset',
                    'message' => "Backing status {$status} for {$assetCode}.",
                    'evidence' => $snapshot->toArray(),
                ]);
            }

            $results[$assetCode] = $snapshot->toArray();
        }

        return $results;
    }

    private function status(?string $ratio, string $liability): string
    {
        if (FinancialDecimal::compare($liability, '0') === 0) {
            return 'HEALTHY';
        }
        if ($ratio === null) {
            return 'UNKNOWN';
        }
        if (FinancialDecimal::compare($ratio, (string) config('finance.backing.critical_ratio', '1.00')) < 0) {
            return 'CRITICAL';
        }
        if (FinancialDecimal::compare($ratio, (string) config('finance.backing.warning_ratio', '1.05')) < 0) {
            return 'WARNING';
        }
        return 'HEALTHY';
    }
}
