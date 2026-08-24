<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceValuationSnapshot;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceValuationService
{
    public function record(string $asset, string $reportingCurrency, string $rate, string $source, ?\DateTimeInterface $valuedAt = null, array $metadata = []): FinanceValuationSnapshot
    {
        return FinanceValuationSnapshot::query()->create([
            'valuation_uuid' => (string) Str::uuid(),
            'asset' => strtoupper($asset),
            'reporting_currency' => strtoupper($reportingCurrency),
            'rate' => FinancialDecimal::normalize($rate),
            'source' => $source,
            'quality' => $metadata['quality'] ?? 'VERIFIED',
            'valued_at' => $valuedAt ?? now(),
            'metadata' => $metadata,
        ]);
    }

    public function historicalRate(string $asset, ?\DateTimeInterface $at = null, ?string $reportingCurrency = null): string
    {
        $row = FinanceValuationSnapshot::query()
            ->where('asset', strtoupper($asset))
            ->where('reporting_currency', strtoupper($reportingCurrency ?? (string) config('finance.reporting_currency', 'USD')))
            ->when($at, fn ($query) => $query->where('valued_at', '<=', $at))
            ->orderByDesc('valued_at')
            ->first();

        if (! $row) {
            if (strtoupper($asset) === strtoupper($reportingCurrency ?? (string) config('finance.reporting_currency', 'USD'))) {
                return '1';
            }
            throw new RuntimeException("Missing finance valuation for {$asset}.");
        }

        return (string) $row->rate;
    }
}
