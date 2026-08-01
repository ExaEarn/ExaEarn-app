<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiOrder;
use App\Models\ExaAiSession;
use App\Models\FuturesMarket;
use App\Models\Market;
use App\Models\TradingSignal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExaAiExecutionService
{
    private const SCALE = 8;

    public function __construct(
        private readonly TradeService $tradeService,
        private readonly FuturesOrderService $futuresOrders,
        private readonly ExaAiRiskService $risk,
    ) {
    }

    public function evaluateActiveSessions(): int
    {
        $count = 0;
        ExaAiSession::query()
            ->with(['user', 'subscription.plan', 'allocation', 'strategy', 'strategyVersion'])
            ->where('status', 'active')
            ->chunkById(50, function ($sessions) use (&$count): void {
                foreach ($sessions as $session) {
                    try {
                        if ($this->evaluateSession($session)) {
                            $count++;
                        }
                    } catch (\Throwable $exception) {
                        $session->metadata = array_merge($session->metadata ?? [], [
                            'last_execution_error' => $exception->getMessage(),
                            'last_execution_error_at' => now()->toISOString(),
                        ]);
                        $session->save();
                    }
                }
            });

        return $count;
    }

    public function evaluateSession(ExaAiSession $session): bool
    {
        $signal = $this->selectSignal($session);
        if (! $signal) {
            return false;
        }

        $existingOpen = $session->orders()
            ->whereIn('status', ['pending', 'open'])
            ->where('pair', $this->normalizePair($signal->symbol))
            ->exists();

        if ($existingOpen) {
            return false;
        }

        $marketType = $this->resolveMarketType($session, $signal);
        $execution = $marketType === 'futures'
            ? $this->executeFutures($session, $signal)
            : $this->executeSpot($session, $signal);

        return $execution instanceof ExaAiOrder;
    }

    private function executeSpot(ExaAiSession $session, TradingSignal $signal): ExaAiOrder
    {
        $pair = $this->normalizePair($signal->symbol);
        $market = Market::query()->where('symbol', $pair)->first();
        if (! $market || $market->status !== 'active') {
            throw new RuntimeException('Spot market is unavailable for ExaAI execution.');
        }

        $quoteBudget = $this->spotQuoteBudget($session);
        $this->risk->assertSessionCanTrade($session, $signal, $quoteBudget);
        $entryPrice = $this->fmt((string) ($signal->suggested_entry ?: $market->last_price));
        if ($this->compare($entryPrice, '0') <= 0) {
            throw new RuntimeException('Spot execution price is invalid.');
        }

        $quantity = $this->div($quoteBudget, $entryPrice);
        if ($this->compare($quantity, '0') <= 0) {
            throw new RuntimeException('Calculated spot quantity is invalid.');
        }

        return DB::transaction(function () use ($entryPrice, $pair, $quantity, $quoteBudget, $session, $signal): ExaAiOrder {
            $this->risk->reserveAllocation($session, $quoteBudget);

            $result = $this->tradeService->placeOrder(
                (int) $session->user_id,
                $pair,
                strtoupper((string) $signal->signal_type) === 'SELL' ? 'sell' : 'buy',
                'market',
                $quantity,
                null,
                [
                    'source' => 'exaai',
                    'exaai' => true,
                    'exaai_session_id' => $session->id,
                    'exaai_strategy_id' => $session->strategy_definition_id,
                    'exaai_strategy_version_id' => $session->strategy_version_id,
                    'exaai_signal_id' => $signal->id,
                    'decision_timestamp' => now()->toISOString(),
                    'signal_confidence' => $signal->confidence,
                ]
            );

            $order = $result['order'] ?? null;
            if (! $order) {
                throw new RuntimeException('Spot order execution did not return an order payload.');
            }

            return ExaAiOrder::query()->create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'strategy_definition_id' => $session->strategy_definition_id,
                'market_type' => 'spot',
                'pair' => $pair,
                'side' => (string) $order->side,
                'order_type' => (string) $order->type,
                'quantity' => (string) $order->amount,
                'entry_price' => $entryPrice,
                'fees' => '0',
                'realized_pnl' => '0',
                'unrealized_pnl' => '0',
                'status' => in_array((string) $order->status, ['filled', 'closed'], true) ? 'closed' : 'open',
                'source_order_uuid' => (string) $order->order_uuid,
                'signal_context' => [
                    'signal_id' => $signal->id,
                    'confidence' => $signal->confidence,
                    'reason' => $signal->reason,
                ],
                'risk_snapshot' => [
                    'reserved_allocation' => $quoteBudget,
                    'entry_price' => $entryPrice,
                ],
                'opened_at' => now(),
                'closed_at' => in_array((string) $order->status, ['filled', 'closed'], true) ? now() : null,
            ]);
        });
    }

    private function executeFutures(ExaAiSession $session, TradingSignal $signal): ExaAiOrder
    {
        $symbol = strtoupper(str_replace('/', '', (string) $signal->symbol));
        $market = FuturesMarket::query()->where('symbol', $symbol)->first();
        if (! $market || $market->status !== 'active') {
            throw new RuntimeException('Futures market is unavailable for ExaAI execution.');
        }

        $marginBudget = $this->futuresMarginBudget($session);
        $this->risk->assertSessionCanTrade($session, $signal, $marginBudget);
        $entryPrice = $this->fmt((string) ($signal->suggested_entry ?: $market->last_price));
        if ($this->compare($entryPrice, '0') <= 0) {
            throw new RuntimeException('Futures execution price is invalid.');
        }

        $leverage = (int) min(max(2, (int) ($session->constraints['leverage'] ?? 3)), (int) $market->max_leverage);
        $notional = $this->mul($marginBudget, (string) $leverage);
        $quantity = $this->div($notional, $entryPrice);
        if ($this->compare($quantity, '0') <= 0) {
            throw new RuntimeException('Calculated futures quantity is invalid.');
        }

        return DB::transaction(function () use ($entryPrice, $leverage, $marginBudget, $quantity, $session, $signal, $symbol): ExaAiOrder {
            $this->risk->reserveAllocation($session, $marginBudget);

            $order = $this->futuresOrders->placeOrder((int) $session->user_id, [
                'symbol' => $symbol,
                'type' => 'market',
                'side' => strtoupper((string) $signal->signal_type) === 'SELL' ? 'short' : 'long',
                'quantity' => $quantity,
                'leverage' => $leverage,
                'source' => 'exaai',
                'metadata' => [
                    'source' => 'exaai',
                    'exaai' => true,
                    'exaai_session_id' => $session->id,
                    'exaai_strategy_id' => $session->strategy_definition_id,
                    'exaai_strategy_version_id' => $session->strategy_version_id,
                    'exaai_signal_id' => $signal->id,
                    'decision_timestamp' => now()->toISOString(),
                    'signal_confidence' => $signal->confidence,
                ],
            ]);

            return ExaAiOrder::query()->create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'strategy_definition_id' => $session->strategy_definition_id,
                'market_type' => 'futures',
                'pair' => $this->normalizePair($symbol),
                'side' => (string) $order->side,
                'order_type' => (string) $order->type,
                'quantity' => (string) $order->quantity,
                'entry_price' => $entryPrice,
                'fees' => '0',
                'realized_pnl' => '0',
                'unrealized_pnl' => '0',
                'status' => in_array((string) $order->status, ['filled', 'closed'], true) ? 'closed' : 'open',
                'source_futures_order_uuid' => (string) $order->order_uuid,
                'signal_context' => [
                    'signal_id' => $signal->id,
                    'confidence' => $signal->confidence,
                    'reason' => $signal->reason,
                ],
                'risk_snapshot' => [
                    'reserved_allocation' => $marginBudget,
                    'entry_price' => $entryPrice,
                    'leverage' => $leverage,
                ],
                'opened_at' => now(),
                'closed_at' => in_array((string) $order->status, ['filled', 'closed'], true) ? now() : null,
            ]);
        });
    }

    private function selectSignal(ExaAiSession $session): ?TradingSignal
    {
        $eligibleMarkets = collect($session->eligible_markets ?? [])->filter()->values();
        if ($eligibleMarkets->isEmpty()) {
            return null;
        }

        return TradingSignal::query()
            ->where('user_id', $session->user_id)
            ->where('is_active', true)
            ->where(function ($query) use ($eligibleMarkets): void {
                foreach ($eligibleMarkets as $pair) {
                    $query->orWhere('symbol', strtoupper(str_replace('/', '', (string) $pair)));
                }
            })
            ->whereIn('signal_type', ['BUY', 'SELL'])
            ->orderByDesc('confidence')
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (TradingSignal $signal): bool => $signal->isValid());
    }

    private function resolveMarketType(ExaAiSession $session, TradingSignal $signal): string
    {
        if ($session->strategy->supports_futures !== true) {
            return 'spot';
        }

        return $session->strategy->risk_level === 'aggressive' ? 'futures' : 'spot';
    }

    private function spotQuoteBudget(ExaAiSession $session): string
    {
        $pct = (string) ($session->constraints['max_position_pct'] ?? '0.10');
        return $this->mul((string) $session->allocation->available_amount, $pct);
    }

    private function futuresMarginBudget(ExaAiSession $session): string
    {
        $pct = (string) ($session->constraints['max_position_pct'] ?? '0.10');
        return $this->mul((string) $session->allocation->available_amount, $pct);
    }

    private function normalizePair(string $symbol): string
    {
        $symbol = strtoupper(str_replace('/', '', $symbol));
        foreach (['USDT', 'USDC', 'BTC', 'ETH'] as $quote) {
            if (str_ends_with($symbol, $quote)) {
                return substr($symbol, 0, -strlen($quote)) . '/' . $quote;
            }
        }

        return $symbol;
    }

    private function fmt(string $value): string
    {
        return bcadd(trim($value), '0', self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        return bcmul($left, $right, self::SCALE);
    }

    private function div(string $left, string $right): string
    {
        return bccomp($right, '0', self::SCALE) === 0 ? '0.00000000' : bcdiv($left, $right, self::SCALE);
    }
}