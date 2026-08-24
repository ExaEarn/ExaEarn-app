<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class SwapPricingService
{
    public function __construct(
        private readonly FxRateService $fxRateService,
        private readonly MarketDataService $marketDataService,
        private readonly PricingPolicyEngine $pricing,
    ) {
    }

    public function price(string $fromCurrency, string $toCurrency, string $amount): array
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);
        $amount = FinancialDecimal::normalize($amount);

        if ($from === $to) {
            throw new RuntimeException('Convert assets must be different.');
        }

        [$routeType, $route] = $this->route($from, $to);
        $rate = $this->rate($from, $to, $routeType);
        $feeQuote = $this->fee($amount, $routeType, $from, $to);
        $fee = $feeQuote['fee_amount'];
        $netAmount = FinancialDecimal::sub($amount, $fee);
        if (FinancialDecimal::compare($netAmount, '0') <= 0) {
            throw new RuntimeException('Convert amount is too small after fees.');
        }

        $receive = FinancialDecimal::mul($netAmount, $rate, 18);

        return [
            'route_type' => $routeType,
            'route' => $route,
            'rate' => FinancialDecimal::normalize($rate, 18),
            'amount_sent' => $amount,
            'fee' => FinancialDecimal::normalize($fee, 18),
            'amount_received' => FinancialDecimal::normalize($receive, 18),
            'price_source' => $this->priceSource($from, $to, $routeType),
            'pricing_decision' => $feeQuote,
        ];
    }

    private function route(string $from, string $to): array
    {
        $fromFiat = $this->isFiat($from);
        $toFiat = $this->isFiat($to);

        if ($fromFiat && $toFiat) {
            return ['fiat_to_fiat', "{$from}->{$to}"];
        }

        if ($fromFiat) {
            return ['fiat_to_crypto', "{$from}->USD->{$to}"];
        }

        if ($toFiat) {
            return ['crypto_to_fiat', "{$from}->USD->{$to}"];
        }

        if ($from === 'USDT' || $to === 'USDT') {
            return ['crypto_direct_usdt', "{$from}->{$to}"];
        }

        return ['crypto_via_usdt', "{$from}->USDT->{$to}"];
    }

    private function rate(string $from, string $to, string $routeType): string
    {
        return match ($routeType) {
            'fiat_to_fiat' => $this->fxRateService->getRate($from, $to),
            'fiat_to_crypto' => FinancialDecimal::div(
                $this->fxRateService->getRate($from, 'USD'),
                $this->assetUsdPrice($to),
                18,
            ),
            'crypto_to_fiat' => FinancialDecimal::mul(
                $this->assetUsdPrice($from),
                $this->fxRateService->getRate('USD', $to),
                18,
            ),
            'crypto_direct_usdt' => $from === 'USDT'
                ? FinancialDecimal::div('1', $this->assetUsdPrice($to), 18)
                : $this->assetUsdPrice($from),
            default => FinancialDecimal::div($this->assetUsdPrice($from), $this->assetUsdPrice($to), 18),
        };
    }

    private function assetUsdPrice(string $asset): string
    {
        if (in_array($asset, ['USD', 'USDT', 'USDC'], true)) {
            return '1';
        }

        $ticker = $this->marketDataService->ticker("{$asset}/USDT");
        $price = (string) ($ticker['last_trade_price'] ?? $ticker['reference_price'] ?? $ticker['last_price'] ?? '0');

        if (FinancialDecimal::compare($price, '0') <= 0) {
            throw new RuntimeException("No fresh price is available for {$asset}/USDT.");
        }

        return $price;
    }

    private function priceSource(string $from, string $to, string $routeType): array
    {
        $symbols = collect([$from, $to])
            ->filter(fn (string $asset): bool => $this->isCrypto($asset) && !in_array($asset, ['USDT', 'USDC'], true))
            ->unique()
            ->map(function (string $asset): array {
                $ticker = $this->marketDataService->ticker("{$asset}/USDT");
                return [
                    'symbol' => "{$asset}/USDT",
                    'source' => $ticker['source'] ?? MarketDataService::SOURCE_REFERENCE,
                    'last_trade_price' => $ticker['last_trade_price'] ?? null,
                    'reference_price' => $ticker['reference_price'] ?? null,
                    'updated_at' => $ticker['updated_at'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'route_type' => $routeType,
            'components' => $symbols,
        ];
    }

    private function fee(string $amount, string $routeType, string $from, string $to): array
    {
        $pct = (string) config('swap.fee_percent', '0.5');
        $legacyFee = FinancialDecimal::mul($amount, FinancialDecimal::div($pct, '100', 18), 18);

        try {
            return $this->pricing->preview([
                'product' => 'CONVERT',
                'operation' => 'FEE',
                'amount' => $amount,
                'asset' => $from,
                'currency' => $from,
                'metadata' => ['to_asset' => $to, 'route_type' => $routeType],
            ]);
        } catch (\Throwable $exception) {
            if ($this->pricing->isEnforced('CONVERT')) {
                throw $exception;
            }

            return [
                'source' => 'legacy_swap_config',
                'gross_amount' => FinancialDecimal::normalize($amount),
                'fee_amount' => FinancialDecimal::normalize($legacyFee),
                'net_amount' => FinancialDecimal::sub($amount, $legacyFee),
                'rate_bps' => FinancialDecimal::mul($pct, '100', 8),
                'fee_policy_snapshot' => ['source' => 'config/swap.php', 'route_type' => $routeType],
            ];
        }
    }

    private function isFiat(string $currency): bool
    {
        return in_array(strtoupper($currency), array_map('strtoupper', config('swap.supported_fiat', [])), true);
    }

    private function isCrypto(string $currency): bool
    {
        return in_array(strtoupper($currency), array_map('strtoupper', config('swap.supported_crypto', [])), true);
    }
}
