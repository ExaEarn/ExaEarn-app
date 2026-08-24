<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesIndexPriceSnapshot;
use App\Models\FuturesMarket;
use Illuminate\Support\Carbon;
use RuntimeException;

class FuturesIndexPriceService
{
    public function calculate(FuturesMarket $market, array $constituents, ?Carbon $now = null): array
    {
        $now ??= now();
        $maxAgeSeconds = (int) config('futures.index.max_constituent_age_seconds', 10);
        $maxDeviationBps = (int) config('futures.index.max_constituent_deviation_bps', 300);
        $healthy = [];

        foreach ($constituents as $row) {
            $price = (string) ($row['price'] ?? '0');
            $timestamp = isset($row['timestamp']) ? Carbon::parse($row['timestamp']) : $now;
            $status = 'HEALTHY';

            if (FinancialDecimal::compare($price, '0') <= 0) {
                $status = 'OUTLIER';
            } elseif ($timestamp->diffInSeconds($now) > $maxAgeSeconds) {
                $status = 'STALE';
            }

            $healthy[] = array_merge($row, ['price' => $price, 'status' => $status, 'timestamp' => $timestamp->toISOString()]);
        }

        $valid = array_values(array_filter($healthy, static fn (array $row): bool => $row['status'] === 'HEALTHY'));
        if (count($valid) < (int) config('futures.index.min_healthy_constituents', 1)) {
            throw new RuntimeException('Insufficient healthy index constituents.');
        }

        $median = $this->median(array_map(static fn (array $row): string => (string) $row['price'], $valid));
        foreach ($valid as $idx => $row) {
            $deviation = FinancialDecimal::compare($median, '0') > 0
                ? FinancialDecimal::mul(FinancialDecimal::div(FinancialDecimal::abs(FinancialDecimal::sub((string) $row['price'], $median)), $median), '10000')
                : '0';
            if (FinancialDecimal::compare($deviation, (string) $maxDeviationBps) > 0) {
                $valid[$idx]['status'] = 'OUTLIER';
            }
        }

        $valid = array_values(array_filter($valid, static fn (array $row): bool => $row['status'] === 'HEALTHY'));
        if (count($valid) < (int) config('futures.index.min_healthy_constituents', 1)) {
            throw new RuntimeException('Insufficient healthy index constituents after outlier filtering.');
        }

        $sum = '0';
        foreach ($valid as $row) {
            $sum = FinancialDecimal::add($sum, (string) $row['price']);
        }
        $index = FinancialDecimal::div($sum, (string) count($valid));

        FuturesIndexPriceSnapshot::query()->create([
            'futures_market_id' => $market->id,
            'symbol' => $market->symbol,
            'index_price' => $index,
            'constituents' => $healthy,
            'status' => 'healthy',
            'calculated_at' => $now,
        ]);

        $market->index_price = $index;
        $market->save();

        return ['index_price' => $index, 'healthy_count' => count($valid), 'constituents' => $healthy];
    }

    private function median(array $prices): string
    {
        usort($prices, static fn (string $a, string $b): int => FinancialDecimal::compare($a, $b));
        $count = count($prices);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $prices[$middle];
        }

        return FinancialDecimal::div(FinancialDecimal::add($prices[$middle - 1], $prices[$middle]), '2');
    }
}
