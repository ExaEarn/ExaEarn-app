<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstitutionalAccount;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotHedge;
use Illuminate\Support\Str;
use RuntimeException;

class MarketMakerHedgeService
{
    public function __construct(
        private readonly MarketMakerCrossProductRiskService $crossRisk,
        private readonly MarketMakerFuturesRiskService $futuresRisk,
        private readonly MarketMakerFairValueService $fairValue,
        private readonly FuturesOrderService $futures,
    ) {
    }

    public function hedge(MarketMakerBot $bot, string $idempotencyKey): MarketMakerBotHedge
    {
        $existing = MarketMakerBotHedge::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->fresh();
        }
        $mode = strtoupper((string) ($bot->configuration['hedge_mode'] ?? 'DISABLED'));
        if (! in_array($mode, ['DISABLED', 'RECOMMEND_ONLY', 'AUTOMATED_WITH_LIMITS'], true)) {
            throw new RuntimeException('Unsupported hedge mode.');
        }
        if ($mode === 'DISABLED') {
            throw new RuntimeException('Futures hedging is disabled for this bot.');
        }
        $risk = $this->crossRisk->exposure($bot);
        $fair = $this->fairValue->fairValue($bot->market_symbol);
        $futuresRisk = $this->futuresRisk->assertCanQuote($bot, $fair, $risk, false);
        $ratio = FinancialDecimal::normalize((string) ($bot->configuration['hedge_ratio'] ?? '0'), 8);
        $maxRatio = FinancialDecimal::normalize((string) ($bot->risk_limits['max_hedge_ratio'] ?? '1'), 8);
        $ratio = FinancialDecimal::min($ratio, $maxRatio, 8);
        $spotNotional = FinancialDecimal::mul((string) $risk['spot_base_inventory'], (string) $fair['fair_value']);
        $targetNotional = FinancialDecimal::mul($spotNotional, $ratio);
        $maxNotional = FinancialDecimal::normalize((string) ($bot->risk_limits['max_hedge_notional'] ?? $targetNotional));
        $targetNotional = FinancialDecimal::min($targetNotional, $maxNotional);
        $quantity = FinancialDecimal::compare($fair['fair_value'], '0') > 0 ? FinancialDecimal::div($targetNotional, (string) $fair['fair_value']) : '0';
        $side = FinancialDecimal::compare((string) $risk['net_delta'], '0') > 0 ? 'short' : 'long';
        $status = 'RECOMMENDED';
        $futuresOrder = null;
        $institution = InstitutionalAccount::query()->findOrFail($bot->institution_id);

        if ($mode === 'AUTOMATED_WITH_LIMITS' && FinancialDecimal::compare($quantity, '0') > 0) {
            $limitPrice = $side === 'short'
                ? FinancialDecimal::mul((string) $fair['fair_value'], '1.001')
                : FinancialDecimal::mul((string) $fair['fair_value'], '0.999');
            $futuresOrder = $this->futures->placeOrder((int) $institution->master_user_id, [
                'symbol' => (string) $risk['futures_market'],
                'type' => 'limit',
                'side' => $side,
                'quantity' => $quantity,
                'price' => $limitPrice,
                'leverage' => (int) ($bot->configuration['futures_leverage'] ?? 1),
                'time_in_force' => 'GTC',
                'post_only' => true,
                'reduce_only' => false,
                'source' => 'market_maker_bot_hedge',
                'metadata' => ['bot_id' => $bot->id, 'strategy_version_id' => $bot->strategy_version_id, 'hedge' => true],
            ]);
            $status = 'SUBMITTED';
        }

        return MarketMakerBotHedge::query()->create([
            'hedge_uuid' => (string) Str::uuid(),
            'bot_id' => $bot->id,
            'strategy_version_id' => $bot->strategy_version_id,
            'spot_market' => $bot->market_symbol,
            'futures_market' => (string) $risk['futures_market'],
            'mode' => $mode,
            'side' => strtoupper($side),
            'target_hedge_ratio' => $ratio,
            'target_notional' => $targetNotional,
            'actual_notional' => $futuresOrder ? (string) $futuresOrder->notional_value : '0',
            'futures_order_id' => $futuresOrder?->id,
            'status' => $status,
            'idempotency_key' => $idempotencyKey,
            'risk_snapshot' => $futuresRisk,
            'metadata' => ['cross_product' => $risk, 'funding_is_costed' => true, 'basis_risk_is_tracked' => true],
        ]);
    }
}
