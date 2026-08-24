<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginLendingPool;
use App\Models\Market;
use App\Models\TradingMarketRiskProfile;
use App\Models\TradingRiskLimit;
use App\Models\TradingUserRiskProfile;
use App\Models\User;
use RuntimeException;

class TradingRiskEngine
{
    public function __construct(
        private readonly CircuitBreakerService $breakers,
        private readonly PriceProtectionService $prices,
        private readonly LedgerService $ledger,
        private readonly CompliancePolicyService $compliance,
    ) {
    }

    public function evaluateOrder(int $userId, string $product, Market|\App\Models\FuturesMarket $market, array $order): array
    {
        $symbol = strtoupper((string) ($market->symbol ?? $order['symbol'] ?? $order['pair'] ?? ''));
        $side = strtolower((string) ($order['side'] ?? ''));
        $type = strtolower((string) ($order['type'] ?? 'limit'));
        $quantity = FinancialDecimal::normalize((string) ($order['amount'] ?? $order['quantity'] ?? '0'));
        $price = isset($order['price']) && $order['price'] !== null ? FinancialDecimal::normalize((string) $order['price']) : null;
        $reduceOnly = (bool) ($order['reduce_only'] ?? false);

        $breaker = $this->breakers->assertAllowsNewRisk($product, $symbol, $reduceOnly);
        if (! $breaker['allowed']) {
            return $this->decision('MARKET_PAUSED', (string) $breaker['reason_code'], 'REJECT', ['breaker' => $breaker]);
        }

        $profile = TradingUserRiskProfile::query()->firstOrCreate(['user_id' => $userId], [
            'risk_tier' => 'DEFAULT',
            'trading_enabled' => true,
            'margin_enabled' => false,
            'futures_enabled' => false,
            'status' => 'ACTIVE',
        ]);
        if (! $profile->trading_enabled || $profile->status !== 'ACTIVE') {
            return $this->decision('ACCOUNT_RESTRICTED', 'ACCOUNT_TRADING_DISABLED', 'REJECT');
        }

        if ($product === 'margin' && ! $profile->margin_enabled && (string) config('margin.mode') !== 'internal') {
            return $this->decision('ACCOUNT_RESTRICTED', 'MARGIN_NOT_ENABLED', 'REJECT');
        }
        if ($product === 'futures' && ! $profile->futures_enabled && ! app()->environment('testing')) {
            return $this->decision('ACCOUNT_RESTRICTED', 'FUTURES_NOT_ENABLED', 'REJECT');
        }

        $complianceProduct = match ($product) {
            'futures' => 'FUTURES',
            'margin' => 'MARGIN',
            default => 'SPOT',
        };
        $complianceAction = $reduceOnly ? 'REDUCE' : (strtolower($side) === 'sell' ? 'SELL' : 'BUY');
        $baseAsset = strtoupper((string) ($market->base_asset ?? $market->base_currency ?? explode('/', $symbol)[0] ?? ''));
        $policy = $this->compliance->decide(User::query()->find($userId), $complianceProduct, [
            'action' => $complianceAction,
            'market_symbol' => $symbol,
            'asset' => $baseAsset,
            'requested_leverage' => $order['leverage'] ?? null,
        ]);
        if (! in_array($policy['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
            return $this->decision('COMPLIANCE_RESTRICTED', (string) $policy['reason_code'], 'REJECT', ['compliance' => $policy]);
        }

        $marketStatus = strtolower((string) ($market->trading_status ?? $market->status ?? 'active'));
        if (! in_array($marketStatus, ['active', 'trading'], true)) {
            return $this->decision('MARKET_PAUSED', 'MARKET_NOT_TRADING', 'REJECT');
        }

        $referencePrice = $price ?: (string) ($market->last_price ?? $market->mark_price ?? '0');
        $notional = FinancialDecimal::mul($quantity, $referencePrice);
        $maxNotional = $this->maxOrderNotional($product, $symbol);
        if (FinancialDecimal::compare($notional, $maxNotional) > 0) {
            return $this->decision('EXPOSURE_LIMIT_EXCEEDED', 'MAX_ORDER_NOTIONAL_EXCEEDED', 'REJECT', [
                'notional' => $notional,
                'max_order_notional' => $maxNotional,
            ]);
        }

        if ($product !== 'futures' && $market instanceof Market) {
            $priceDecision = $this->prices->validateOrderPrice($market, $price, $side, $type);
            if (! $priceDecision['allowed']) {
                return $this->decision('PRICE_INVALID', (string) $priceDecision['reason_code'], 'REJECT', $priceDecision);
            }
        }

        if ($product === 'margin' && isset($order['auto_borrow_asset'], $order['auto_borrow_amount']) && FinancialDecimal::compare((string) $order['auto_borrow_amount'], '0') > 0) {
            $pool = MarginLendingPool::query()->where('asset', strtoupper((string) $order['auto_borrow_asset']))->first();
            if (! $pool || $pool->status !== 'ENABLED' || FinancialDecimal::compare((string) $pool->available_liquidity, (string) $order['auto_borrow_amount']) < 0) {
                return $this->decision('LIQUIDITY_INSUFFICIENT', 'MARGIN_LENDING_POOL_INSUFFICIENT', 'REJECT');
            }
        }

        if ($product === 'futures') {
            $maxLeverage = $this->maxLeverage($product, $symbol);
            if (($policy['effective_max_leverage'] ?? null) !== null) {
                $maxLeverage = min($maxLeverage, (int) $policy['effective_max_leverage']);
            }
            if ((int) ($order['leverage'] ?? 1) > $maxLeverage) {
                return $this->decision('LEVERAGE_LIMIT_EXCEEDED', 'MAX_LEVERAGE_EXCEEDED', 'REJECT');
            }
        }

        return $this->decision('ALLOW', 'RISK_OK', 'ALLOW', [
            'notional' => $notional,
            'max_order_notional' => $maxNotional,
        ]);
    }

    public function assertOrderAllowed(int $userId, string $product, Market|\App\Models\FuturesMarket $market, array $order): array
    {
        $decision = $this->evaluateOrder($userId, $product, $market, $order);
        if ($decision['action'] !== 'ALLOW') {
            throw new RuntimeException('Trading risk check rejected order: ' . $decision['reason_code']);
        }

        return $decision;
    }

    private function maxOrderNotional(string $product, string $symbol): string
    {
        $marketProfile = TradingMarketRiskProfile::query()
            ->where('market_symbol', $symbol)
            ->where('product', $product)
            ->where('status', 'ACTIVE')
            ->first();
        if ($marketProfile?->max_order_notional) {
            return (string) $marketProfile->max_order_notional;
        }

        $limit = TradingRiskLimit::query()
            ->where('scope', 'DEFAULT')
            ->where('product', $product)
            ->where('status', 'ACTIVE')
            ->first();

        return (string) ($limit?->max_order_notional ?? config('trading_operations.default_max_order_notional'));
    }

    private function maxLeverage(string $product, string $symbol): int
    {
        $profile = TradingMarketRiskProfile::query()
            ->where('market_symbol', $symbol)
            ->where('product', $product)
            ->where('status', 'ACTIVE')
            ->first();

        return (int) ($profile?->max_leverage ?? config('trading_operations.default_max_leverage'));
    }

    private function decision(string $status, string $reasonCode, string $action, array $metadata = []): array
    {
        return [
            'status' => $status,
            'reason_code' => $reasonCode,
            'action' => $action,
            'metadata' => $metadata,
            'evaluated_at' => now()->toISOString(),
        ];
    }
}
