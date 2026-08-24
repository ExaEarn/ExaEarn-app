<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarket;
use RuntimeException;

class FuturesRiskEngineService
{
    private const SCALE = 8;

    public function __construct(
        private readonly FuturesInstrumentService $instruments,
        private readonly FuturesMarginService $margin,
        private readonly BalanceProjectionService $balances,
        private readonly LedgerService $ledger,
        private readonly CrossMarginHealthService $crossMargin,
    ) {
    }

    public function validateOrderRisk(
        int $userId,
        FuturesMarket $market,
        string $side,
        int $leverage,
        string $notional,
        string $marginRequired,
        array $context = [],
    ): void {
        if (!in_array(strtolower($side), ['long', 'short'], true)) {
            throw new RuntimeException('Invalid futures side.');
        }

        if ($leverage < (int) $market->min_leverage || $leverage > (int) $market->max_leverage) {
            throw new RuntimeException('Leverage out of allowed range.');
        }

        $tier = $this->instruments->tierForNotional($market, $notional);
        if ($leverage > (int) ($tier['max_leverage'] ?? $market->max_leverage)) {
            throw new RuntimeException('Leverage exceeds the active risk tier.');
        }

        if ($this->compare($notional, '0') <= 0) {
            throw new RuntimeException('Order notional must be greater than zero.');
        }

        if ($this->compare($marginRequired, '0') <= 0) {
            throw new RuntimeException('Margin requirement must be greater than zero.');
        }

        $maintenanceMargin = $this->margin->maintenanceMargin($market, $notional);

        if ($this->compare($marginRequired, $maintenanceMargin) <= 0) {
            throw new RuntimeException('Initial margin must exceed maintenance margin.');
        }

        $settlementAsset = strtoupper((string) ($market->settlement_asset ?: 'USDT'));
        $marginMode = strtolower((string) ($context['margin_mode'] ?? 'cross'));
        if ($marginMode === 'cross') {
            $this->crossMargin->assertCanReserve($userId, $settlementAsset, $marginRequired);
        } else {
            $account = $this->ledger->getOrCreateAccount($userId, 'futures', $settlementAsset);
            $available = (string) $this->balances->accountProjection($account)['available'];
            if ($this->compare($available, $marginRequired) < 0) {
                throw new RuntimeException('Insufficient futures margin balance.');
            }
        }

        $this->validatePriceBand($market, (string) ($context['price'] ?? $market->last_price));
        $this->validateReduceOnly($userId, $market, strtolower($side), (string) ($context['quantity'] ?? '0'), (bool) ($context['reduce_only'] ?? false));
    }

    public function validatePriceBand(FuturesMarket $market, string $price): void
    {
        $reference = (string) ($market->mark_price ?: $market->index_price ?: $market->last_price);
        if ($this->compare($reference, '0') <= 0 || $this->compare($price, '0') <= 0) {
            return;
        }

        $band = (string) ($market->price_band_bps ?? 500);
        $maxDeviation = FinancialDecimal::mul($reference, FinancialDecimal::div($band, '10000'));
        $lower = FinancialDecimal::sub($reference, $maxDeviation);
        $upper = FinancialDecimal::add($reference, $maxDeviation);

        if ($this->compare($price, $lower) < 0 || $this->compare($price, $upper) > 0) {
            throw new RuntimeException('Futures order price is outside the allowed price band.');
        }
    }

    public function validateReduceOnly(int $userId, FuturesMarket $market, string $side, string $quantity, bool $reduceOnly): void
    {
        if (!$reduceOnly) {
            return;
        }

        $oppositePositionSide = $side === 'long' ? 'short' : 'long';
        $position = \App\Models\FuturesPosition::query()
            ->where('user_id', $userId)
            ->where('futures_market_id', $market->id)
            ->where('side', $oppositePositionSide)
            ->where('status', 'open')
            ->first();

        if (!$position || $this->compare((string) $position->quantity, '0') <= 0) {
            throw new RuntimeException('Reduce-only order requires an opposite open position.');
        }

        if ($this->compare($quantity, (string) $position->quantity) > 0) {
            throw new RuntimeException('Reduce-only order quantity exceeds open position size.');
        }
    }

    private function compare(string $a, string $b): int
    {
        return FinancialDecimal::compare($a, $b, self::SCALE);
    }
}
