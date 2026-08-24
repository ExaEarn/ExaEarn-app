<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Market;
use App\Models\Order;
use App\Models\Trade;
use App\Models\TradingPriceSnapshot;
use App\Models\TradingPriceSourceHealth;
use Illuminate\Support\Str;

class PriceProtectionService
{
    public function validateOrderPrice(Market $market, ?string $orderPrice, string $side, string $type): array
    {
        $lastTrade = Trade::query()->where('market_id', $market->id)->latest('executed_at')->latest('id')->first();
        $reference = (string) ($lastTrade?->price ?? $market->last_price ?? '0');
        $trustedAnchor = $lastTrade !== null || TradingPriceSnapshot::query()
            ->where('market_symbol', strtoupper((string) $market->symbol))
            ->whereIn('price_type', ['REFERENCE', 'INDEX', 'MARK', 'LAST'])
            ->exists() || TradingPriceSourceHealth::query()
            ->where('market_symbol', strtoupper((string) $market->symbol))
            ->where('status', 'HEALTHY')
            ->exists();
        $bestBid = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'buy')
            ->whereIn('status', ['open', 'partially_filled'])
            ->max('price');
        $bestAsk = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->min('price');

        if (FinancialDecimal::compare($reference, '0') <= 0 && $orderPrice !== null && FinancialDecimal::compare($orderPrice, '0') > 0 && $type === 'limit') {
            return ['allowed' => true, 'reason_code' => 'PRICE_BOOTSTRAP', 'quality' => ['status' => 'BOOTSTRAP', 'trusted_anchor' => false]];
        }

        if (! $trustedAnchor) {
            return ['allowed' => true, 'reason_code' => 'PRICE_BOOTSTRAP', 'quality' => ['status' => 'BOOTSTRAP', 'trusted_anchor' => false]];
        }

        $quality = $this->quality($market->symbol, $reference, $lastTrade?->executed_at?->toISOString() ?? now()->toISOString(), [
            'best_bid' => $bestBid,
            'best_ask' => $bestAsk,
        ]);
        if ($quality['status'] !== 'VALID') {
            return ['allowed' => false, 'reason_code' => 'PRICE_INVALID', 'quality' => $quality];
        }

        if ($trustedAnchor && $type === 'limit' && $orderPrice !== null && FinancialDecimal::compare($orderPrice, '0') > 0) {
            $deviation = $this->deviationBps($orderPrice, $reference);
            if (FinancialDecimal::compare($deviation, (string) config('trading_operations.max_order_price_deviation_bps')) > 0) {
                return [
                    'allowed' => false,
                    'reason_code' => 'PRICE_DEVIATION_LIMIT',
                    'quality' => array_merge($quality, ['deviation_bps' => $deviation]),
                ];
            }
        }

        return ['allowed' => true, 'reason_code' => 'PRICE_OK', 'quality' => $quality];
    }

    public function quality(string $symbol, string $referencePrice, ?string $timestamp = null, array $book = []): array
    {
        if (FinancialDecimal::compare($referencePrice, '0') <= 0) {
            $this->recordSourceHealth('market_data', $symbol, 'INVALID', $referencePrice, 'Non-positive price');
            return ['status' => 'INVALID', 'reason_code' => 'PRICE_NON_POSITIVE'];
        }

        $seenAt = $timestamp ? \Carbon\Carbon::parse($timestamp) : now();
        $ageMs = abs($seenAt->diffInMilliseconds(now()));
        if ($ageMs > (int) config('trading_operations.price_feed_max_age_ms')) {
            $this->recordSourceHealth('market_data', $symbol, 'STALE', $referencePrice, 'Price feed stale');
            return ['status' => 'STALE', 'reason_code' => 'PRICE_STALE', 'age_ms' => $ageMs];
        }

        if (isset($book['best_bid'], $book['best_ask']) && $book['best_bid'] !== null && $book['best_ask'] !== null) {
            $spread = FinancialDecimal::sub((string) $book['best_ask'], (string) $book['best_bid']);
            $spreadBps = FinancialDecimal::compare($referencePrice, '0') > 0
                ? FinancialDecimal::mul(FinancialDecimal::div($spread, $referencePrice), '10000')
                : '0';
            if (FinancialDecimal::compare($spreadBps, (string) config('trading_operations.maximum_spread_bps')) > 0) {
                return ['status' => 'WIDE_SPREAD', 'reason_code' => 'SPREAD_TOO_WIDE', 'spread_bps' => $spreadBps];
            }
        }

        $this->recordSourceHealth('market_data', $symbol, 'HEALTHY', $referencePrice);
        $this->recordSnapshot($symbol, 'reference', $referencePrice, 'market_data', $seenAt);

        return ['status' => 'VALID', 'reason_code' => 'PRICE_OK', 'age_ms' => $ageMs];
    }

    public function calculateIndex(string $symbol, array $sources): array
    {
        $valid = [];
        $rejected = [];
        foreach ($sources as $source) {
            $price = (string) ($source['price'] ?? '0');
            if (FinancialDecimal::compare($price, '0') <= 0) {
                $rejected[] = array_merge($source, ['reason' => 'non_positive']);
                continue;
            }
            $valid[] = array_merge($source, ['price' => $price]);
        }

        if ($valid === []) {
            return ['status' => 'INVALID', 'reason_code' => 'NO_VALID_PRICE_SOURCE', 'index_price' => null, 'rejected_sources' => $rejected];
        }

        $prices = array_map(static fn (array $row): string => (string) $row['price'], $valid);
        usort($prices, static fn (string $a, string $b): int => FinancialDecimal::compare($a, $b));
        $mid = intdiv(count($prices), 2);
        $index = count($prices) % 2 === 1 ? $prices[$mid] : FinancialDecimal::div(FinancialDecimal::add($prices[$mid - 1], $prices[$mid]), '2');
        $this->recordSnapshot($symbol, 'index', $index, 'phase7_median', now(), $valid, $rejected);

        return ['status' => 'VALID', 'reason_code' => 'INDEX_OK', 'index_price' => $index, 'constituents' => $valid, 'rejected_sources' => $rejected];
    }

    public function markPrice(string $symbol, string $indexPrice, string $lastPrice): array
    {
        if (FinancialDecimal::compare($indexPrice, '0') <= 0 || FinancialDecimal::compare($lastPrice, '0') <= 0) {
            return ['status' => 'INVALID', 'reason_code' => 'MARK_INPUT_INVALID', 'mark_price' => null];
        }

        $deviation = $this->deviationBps($lastPrice, $indexPrice);
        if (FinancialDecimal::compare($deviation, (string) config('trading_operations.max_mark_price_deviation_bps')) > 0) {
            $lastPrice = $indexPrice;
        }

        $mark = FinancialDecimal::div(FinancialDecimal::add($indexPrice, $lastPrice), '2');
        $this->recordSnapshot($symbol, 'mark', $mark, 'phase7_mark', now(), [
            ['source' => 'index', 'price' => $indexPrice],
            ['source' => 'last', 'price' => $lastPrice],
        ]);

        return ['status' => 'VALID', 'reason_code' => 'MARK_OK', 'mark_price' => $mark, 'deviation_bps' => $deviation];
    }

    private function deviationBps(string $a, string $b): string
    {
        if (FinancialDecimal::compare($b, '0') <= 0) {
            return '0';
        }

        return FinancialDecimal::mul(FinancialDecimal::div(FinancialDecimal::abs(FinancialDecimal::sub($a, $b)), $b), '10000');
    }

    private function recordSnapshot(string $symbol, string $type, string $price, string $source, \DateTimeInterface $timestamp, array $constituents = [], array $rejected = []): void
    {
        TradingPriceSnapshot::query()->create([
            'snapshot_id' => (string) Str::uuid(),
            'market_symbol' => strtoupper($symbol),
            'product' => 'spot',
            'price_type' => strtoupper($type),
            'price' => $price,
            'source' => $source,
            'source_timestamp' => $timestamp,
            'constituents' => $constituents,
            'rejected_sources' => $rejected,
            'status' => 'VALID',
            'calculation_version' => 'phase7-v1',
        ]);
    }

    private function recordSourceHealth(string $source, string $symbol, string $status, ?string $price = null, ?string $error = null): void
    {
        TradingPriceSourceHealth::query()->updateOrCreate([
            'source' => $source,
            'market_symbol' => strtoupper($symbol),
        ], [
            'status' => $status,
            'last_price' => $price,
            'last_seen_at' => now(),
            'last_error' => $error,
        ]);
    }
}
