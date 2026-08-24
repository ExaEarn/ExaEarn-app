<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyLeadTradeEvent;
use App\Models\CopyOrder;
use App\Models\CopyProfitShareAccrual;
use App\Models\CopyRelationship;
use App\Models\CopyStrategyPosition;
use App\Models\FuturesMarket;
use App\Models\Market;
use App\Models\Order;
use App\Models\Trader;
use App\Jobs\ProcessCopyFollowerDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CopyTradingService
{
    private const SCALE = 8;

    public function __construct(
        private readonly FuturesOrderService $orderService,
        private readonly LedgerService $ledger,
        private readonly BalanceProjectionService $balances,
        private readonly TradeService $spotOrders,
        private readonly CopyRealtimeService $realtime,
        private readonly CopySurveillanceService $surveillance,
    ) {
    }

    public function applyLeadTrader(int $userId, array $payload): Trader
    {
        return DB::transaction(function () use ($payload, $userId): Trader {
            $trader = Trader::query()->firstOrNew(['user_id' => $userId]);
            $trader->fill([
                'lead_trader_uuid' => $trader->lead_trader_uuid ?: (string) Str::uuid(),
                'display_name' => (string) ($payload['display_name'] ?? 'ExaEarn Trader'),
                'bio' => $payload['bio'] ?? null,
                'is_master_trader' => false,
                'status' => 'pending',
                'supported_products' => $payload['supported_products'] ?? ['futures'],
                'profit_share_rate' => (string) ($payload['profit_share_rate'] ?? config('copy_trading.default_profit_share_rate', '0.10')),
                'metadata' => array_merge($trader->metadata ?? [], [
                    'terms_accepted_at' => now()->toISOString(),
                    'application_source' => 'api',
                ]),
            ]);
            $trader->save();

            return $trader->fresh();
        });
    }

    public function activateLeadTrader(int $traderId, int $adminId): Trader
    {
        /** @var Trader $trader */
        $trader = Trader::query()->findOrFail($traderId);
        $trader->forceFill([
            'is_master_trader' => true,
            'status' => 'active',
            'approved_at' => now(),
            'metadata' => array_merge($trader->metadata ?? [], ['approved_by' => $adminId]),
        ])->save();

        return $trader->fresh();
    }

    public function followTrader(int $followerId, int $traderId, string|float|int $amountAllocated, string $riskLevel = 'medium', array $settings = []): CopyRelationship
    {
        /** @var Trader $trader */
        $trader = Trader::query()
            ->where('id', $traderId)
            ->where('is_master_trader', true)
            ->where('status', 'active')
            ->firstOrFail();

        if ($followerId === (int) $trader->user_id) {
            throw new RuntimeException('Cannot follow yourself');
        }

        $allocation = FinancialDecimal::normalize((string) $amountAllocated);
        if (FinancialDecimal::compare($allocation, '0') <= 0) {
            throw new RuntimeException('Copy allocation must be greater than zero.');
        }

        return DB::transaction(function () use ($allocation, $followerId, $riskLevel, $settings, $trader): CopyRelationship {
            $existing = CopyRelationship::query()
                ->where('follower_id', $followerId)
                ->where('trader_id', $trader->id)
                ->whereIn('status', ['active', 'paused'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                throw new RuntimeException('Already following this trader');
            }

            $this->assertLeadCapacity($trader, $allocation);

            $productScope = (string) ($settings['product_scope'] ?? 'futures');
            $accountType = $productScope === 'futures' ? 'futures' : 'unified_trading';
            $fundingAccount = $this->ledger->getOrCreateAccount($followerId, $accountType, 'USDT');
            $available = (string) $this->balances->accountProjection($fundingAccount)['available'];
            if (FinancialDecimal::compare($available, $allocation) < 0) {
                throw new RuntimeException('Insufficient balance for copy allocation.');
            }

            $relationship = CopyRelationship::query()->create([
                'relationship_uuid' => (string) Str::uuid(),
                'follower_id' => $followerId,
                'trader_id' => $trader->id,
                'amount_allocated' => $allocation,
                'copy_available' => $allocation,
                'copy_locked' => '0',
                'copy_pnl' => '0',
                'risk_level' => $riskLevel,
                'product_scope' => $productScope,
                'copy_mode' => (string) ($settings['copy_mode'] ?? 'proportional'),
                'fixed_amount_per_trade' => $settings['fixed_amount_per_trade'] ?? null,
                'fixed_ratio' => $settings['fixed_ratio'] ?? null,
                'max_amount_per_trade' => $settings['max_amount_per_trade'] ?? $allocation,
                'max_daily_loss' => $settings['max_daily_loss'] ?? null,
                'max_drawdown' => $settings['max_drawdown'] ?? null,
                'max_leverage' => (int) ($settings['max_leverage'] ?? config('copy_trading.default_max_leverage', 3)),
                'margin_preference' => (string) ($settings['margin_preference'] ?? 'isolated'),
                'allowed_symbols' => $settings['allowed_symbols'] ?? null,
                'high_water_mark' => $allocation,
                'status' => 'active',
                'metadata' => ['risk_disclosure_accepted_at' => now()->toISOString()],
            ]);

            $trader->incrementFollowers();
            $trader->copy_aum = FinancialDecimal::add((string) $trader->copy_aum, $allocation);
            $trader->save();

            $this->surveillance->evaluateRelationship($relationship->fresh(['trader.user', 'follower']));
            $this->realtime->record($followerId, 'copy.relationship', [
                'relationship_id' => $relationship->relationship_uuid,
                'lead_trader_id' => $trader->lead_trader_uuid,
                'status' => $relationship->status,
                'amount_allocated' => (string) $relationship->amount_allocated,
            ]);

            return $relationship->fresh(['trader', 'follower']);
        });
    }

    public function unfollowTrader(int $followerId, int $traderId): bool
    {
        $relationship = CopyRelationship::query()
            ->where('follower_id', $followerId)
            ->where('trader_id', $traderId)
            ->whereIn('status', ['active', 'paused'])
            ->firstOrFail();

        DB::transaction(function () use ($relationship): void {
            $relationship->status = 'stopped';
            $relationship->save();
            $relationship->trader?->decrementFollowers();
        });

        return true;
    }

    public function recordLeadExecution(int $leadTraderId, array $payload): CopyLeadTradeEvent
    {
        return DB::transaction(function () use ($leadTraderId, $payload): CopyLeadTradeEvent {
            /** @var Trader $trader */
            $trader = Trader::query()->where('id', $leadTraderId)->lockForUpdate()->firstOrFail();
            if (!$trader->is_master_trader || $trader->status !== 'active') {
                throw new RuntimeException('Lead trader is not active.');
            }

            $product = (string) ($payload['product'] ?? 'futures');
            $leadTradeId = (string) $payload['lead_trade_id'];
            $existing = CopyLeadTradeEvent::query()
                ->where('lead_trader_id', $trader->id)
                ->where('product', $product)
                ->where('lead_trade_id', $leadTradeId)
                ->first();
            if ($existing) {
                return $existing;
            }

            $sequence = ((int) CopyLeadTradeEvent::query()->where('lead_trader_id', $trader->id)->max('sequence')) + 1;

            return CopyLeadTradeEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'lead_trader_id' => $trader->id,
                'lead_user_id' => $trader->user_id,
                'product' => $product,
                'symbol' => strtoupper((string) $payload['symbol']),
                'side' => strtolower((string) $payload['side']),
                'position_effect' => (string) ($payload['position_effect'] ?? 'open'),
                'lead_order_id' => $payload['lead_order_id'] ?? null,
                'lead_trade_id' => $leadTradeId,
                'execution_price' => FinancialDecimal::normalize((string) $payload['execution_price']),
                'executed_quantity' => FinancialDecimal::normalize((string) $payload['executed_quantity']),
                'leverage' => (int) ($payload['leverage'] ?? 1),
                'margin_mode' => (string) ($payload['margin_mode'] ?? 'cross'),
                'sequence' => $sequence,
                'executed_at' => $payload['executed_at'] ?? now(),
                'metadata' => $payload['metadata'] ?? [],
            ]);
        });
    }

    public function fanoutLeadExecution(CopyLeadTradeEvent $event): array
    {
        return CopyRelationship::query()
            ->where('trader_id', $event->lead_trader_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (CopyRelationship $relationship): CopyOrder => $this->processFollowerCopy($relationship, $event))
            ->all();
    }

    public function queueFanoutLeadExecution(CopyLeadTradeEvent $event, int $chunkSize = 500, bool $dispatch = true): int
    {
        $queued = 0;
        $queue = $this->priorityFor($event) <= 10 ? 'copy-high' : 'copy-normal';

        CopyRelationship::query()
            ->where('trader_id', $event->lead_trader_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(max(1, min($chunkSize, 1000)), function ($relationships) use ($event, $queue, $dispatch, &$queued): void {
                foreach ($relationships as $relationship) {
                    if ($dispatch) {
                        ProcessCopyFollowerDecision::dispatch((int) $relationship->id, (int) $event->id)->onQueue($queue);
                    }
                    $queued++;
                }
            });

        return $queued;
    }

    public function replicateTrade(int $traderId, array $tradePayload): void
    {
        $event = $this->recordLeadExecution($traderId, [
            'product' => $tradePayload['product'] ?? 'futures',
            'symbol' => $tradePayload['symbol'],
            'side' => $tradePayload['side'],
            'position_effect' => $tradePayload['position_effect'] ?? 'open',
            'lead_order_id' => $tradePayload['lead_order_id'] ?? null,
            'lead_trade_id' => $tradePayload['lead_trade_id'] ?? (string) Str::uuid(),
            'execution_price' => $tradePayload['execution_price'] ?? $tradePayload['price'],
            'executed_quantity' => $tradePayload['executed_quantity'] ?? $tradePayload['quantity'],
            'leverage' => $tradePayload['leverage'] ?? 1,
            'margin_mode' => $tradePayload['margin_mode'] ?? 'cross',
            'executed_at' => $tradePayload['executed_at'] ?? now(),
            'metadata' => $tradePayload['metadata'] ?? [],
        ]);
        $this->fanoutLeadExecution($event);
    }

    public function processFollowerCopy(CopyRelationship $relationship, CopyLeadTradeEvent $event): CopyOrder
    {
        return DB::transaction(function () use ($event, $relationship): CopyOrder {
            $existing = CopyOrder::query()
                ->where('copy_relationship_id', $relationship->id)
                ->where('lead_trade_event_id', $event->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $copyOrder = CopyOrder::query()->create([
                'copy_order_uuid' => (string) Str::uuid(),
                'copy_relationship_id' => $relationship->id,
                'lead_trade_event_id' => $event->id,
                'follower_user_id' => $relationship->follower_id,
                'status' => 'queued',
                'priority' => $this->priorityFor($event),
                'product' => $event->product,
                'symbol' => $event->symbol,
                'side' => $event->side,
                'lead_execution_price' => (string) $event->execution_price,
                'queued_at' => now(),
                'metadata' => ['sequence' => $event->sequence],
            ]);

            try {
                if ($this->isStaleOpeningEvent($event)) {
                    return $this->skip($copyOrder, 'SKIPPED_STALE_EVENT');
                }

                if (!$this->relationshipSupportsProduct($relationship, (string) $event->product)) {
                    return $this->skip($copyOrder, 'PRODUCT_NOT_ALLOWED');
                }

                if ($event->product !== 'futures') {
                    if ($event->product === 'spot') {
                        return $this->processSpotCopy($relationship, $event, $copyOrder);
                    }

                    return $this->skip($copyOrder, 'PRODUCT_NOT_SUPPORTED');
                }

                $quantity = $this->targetQuantity($relationship, $event);
                $copyOrder->target_quantity = $quantity;
                if (FinancialDecimal::compare($quantity, '0', self::SCALE) <= 0) {
                    return $this->skip($copyOrder, 'BELOW_MINIMUM_ORDER');
                }

                $market = FuturesMarket::query()->where('symbol', $event->symbol)->first();
                if (!$market || $market->status !== 'active') {
                    return $this->skip($copyOrder, 'MARKET_UNAVAILABLE');
                }

                $allowed = $relationship->allowed_symbols;
                if (is_array($allowed) && $allowed !== [] && !in_array($event->symbol, array_map('strtoupper', $allowed), true)) {
                    return $this->skip($copyOrder, 'ASSET_NOT_ALLOWED');
                }

                $leverage = min((int) $event->leverage, (int) $relationship->max_leverage, (int) $market->max_leverage);
                if ($leverage <= 0) {
                    return $this->skip($copyOrder, 'MAX_LEVERAGE');
                }

                $maxAmount = $relationship->max_amount_per_trade ? (string) $relationship->max_amount_per_trade : (string) $relationship->amount_allocated;
                $notional = FinancialDecimal::mul((string) $event->execution_price, $quantity);
                if (FinancialDecimal::compare($notional, $maxAmount) > 0) {
                    $quantity = FinancialDecimal::div($maxAmount, (string) $event->execution_price, self::SCALE);
                    $copyOrder->target_quantity = $quantity;
                }

                $order = $this->orderService->placeOrder((int) $relationship->follower_id, [
                    'symbol' => $event->symbol,
                    'type' => 'market',
                    'side' => $event->side,
                    'quantity' => $quantity,
                    'leverage' => $leverage,
                    'reduce_only' => in_array($event->position_effect, ['partial_close', 'close', 'reduce'], true),
                    'margin_mode' => $relationship->margin_preference === 'follow_lead' ? $event->margin_mode : $relationship->margin_preference,
                    'source' => 'copy_trading',
                    'client_order_id' => 'copy:' . $copyOrder->copy_order_uuid,
                    'metadata' => [
                        'copy_relationship_id' => $relationship->id,
                        'lead_trade_event_id' => $event->id,
                        'lead_trader_id' => $event->lead_trader_id,
                        'lead_trade_id' => $event->lead_trade_id,
                    ],
                ]);

                $copyOrder->status = 'executing';
                $copyOrder->follower_futures_order_id = $order->id;
                $copyOrder->submitted_quantity = $quantity;
                $copyOrder->submitted_at = now();
                $copyOrder->risk_snapshot = [
                    'leverage' => $leverage,
                    'margin_mode' => $relationship->margin_preference,
                    'max_amount_per_trade' => $maxAmount,
                ];
                $copyOrder->save();

                $this->upsertStrategyPosition($relationship, $event, $quantity);
                $this->realtime->record((int) $relationship->follower_id, 'copy.order', [
                    'copy_order_id' => $copyOrder->copy_order_uuid,
                    'lead_trade_event_id' => $event->event_id,
                    'product' => 'futures',
                    'symbol' => $event->symbol,
                    'status' => $copyOrder->status,
                    'follower_order_id' => $order->order_uuid,
                ]);

                return $copyOrder->fresh(['followerOrder']);
            } catch (\Throwable $exception) {
                $copyOrder->status = 'failed';
                $copyOrder->reason_code = $this->reasonFromException($exception);
                $copyOrder->metadata = array_merge($copyOrder->metadata ?? [], ['error' => $exception->getMessage()]);
                $copyOrder->completed_at = now();
                $copyOrder->save();

                return $copyOrder->fresh();
            }
        });
    }

    public function accrueProfitShare(CopyRelationship $relationship, string $currentEquity, string $asset = 'USDT'): ?CopyProfitShareAccrual
    {
        return DB::transaction(function () use ($asset, $currentEquity, $relationship): ?CopyProfitShareAccrual {
            /** @var CopyRelationship $relationship */
            $relationship = CopyRelationship::query()->lockForUpdate()->findOrFail($relationship->id);
            $equity = FinancialDecimal::normalize($currentEquity);
            $highWater = FinancialDecimal::normalize((string) $relationship->high_water_mark);
            if (FinancialDecimal::compare($equity, $highWater) <= 0) {
                return null;
            }

            $trader = $relationship->trader()->lockForUpdate()->firstOrFail();
            $eligibleProfit = FinancialDecimal::sub($equity, $highWater);
            $rate = FinancialDecimal::normalize((string) $trader->profit_share_rate);
            $amount = FinancialDecimal::mul($eligibleProfit, $rate);

            $accrual = CopyProfitShareAccrual::query()->create([
                'accrual_id' => (string) Str::uuid(),
                'copy_relationship_id' => $relationship->id,
                'lead_trader_id' => $relationship->trader_id,
                'follower_user_id' => $relationship->follower_id,
                'asset' => strtoupper($asset),
                'eligible_profit' => $eligibleProfit,
                'profit_share_rate' => $rate,
                'accrued_amount' => $amount,
                'high_water_mark_before' => $highWater,
                'high_water_mark_after' => $equity,
                'status' => 'accrued',
                'metadata' => ['source' => 'copy_profit_share'],
            ]);

            $relationship->high_water_mark = $equity;
            $relationship->save();

            $this->realtime->record((int) $relationship->follower_id, 'copy.profit_share', [
                'accrual_id' => $accrual->accrual_id,
                'eligible_profit' => $eligibleProfit,
                'accrued_amount' => $amount,
                'high_water_mark_after' => $equity,
            ]);

            return $accrual;
        });
    }

    public function getTraderFollowers(int $traderId): array
    {
        return CopyRelationship::query()->where('trader_id', $traderId)->with('follower')->get()->toArray();
    }

    public function getUserFollowing(int $userId): array
    {
        return CopyRelationship::query()->where('follower_id', $userId)->with('trader.user')->get()->toArray();
    }

    public function updateTraderPerformance(int $traderId, float|string $performanceScore): void
    {
        Trader::query()->where('id', $traderId)->update(['performance_score' => FinancialDecimal::normalize((string) $performanceScore, 4)]);
    }

    public function pauseCopyTrading(int $followerId, int $traderId): bool
    {
        return (bool) CopyRelationship::query()->where('follower_id', $followerId)->where('trader_id', $traderId)->update(['status' => 'paused']);
    }

    public function resumeCopyTrading(int $followerId, int $traderId): bool
    {
        return (bool) CopyRelationship::query()->where('follower_id', $followerId)->where('trader_id', $traderId)->update(['status' => 'active']);
    }

    private function targetQuantity(CopyRelationship $relationship, CopyLeadTradeEvent $event): string
    {
        $price = (string) $event->execution_price;
        $mode = strtolower((string) $relationship->copy_mode);

        if ($mode === 'fixed_amount') {
            $amount = (string) ($relationship->fixed_amount_per_trade ?: $relationship->max_amount_per_trade ?: $relationship->amount_allocated);
            return FinancialDecimal::div($amount, $price, self::SCALE);
        }

        if ($mode === 'fixed_ratio') {
            $ratio = (string) ($relationship->fixed_ratio ?: '1');
            return FinancialDecimal::mul((string) $event->executed_quantity, $ratio, self::SCALE);
        }

        $leadNotional = FinancialDecimal::mul((string) $event->execution_price, (string) $event->executed_quantity);
        $leadEquity = (string) data_get($event->metadata, 'lead_strategy_equity', $leadNotional);
        $proportion = FinancialDecimal::compare($leadEquity, '0') > 0
            ? FinancialDecimal::div($leadNotional, $leadEquity, self::SCALE)
            : '0';
        $followerNotional = FinancialDecimal::mul((string) $relationship->amount_allocated, $proportion, self::SCALE);

        return FinancialDecimal::div($followerNotional, $price, self::SCALE);
    }

    private function processSpotCopy(CopyRelationship $relationship, CopyLeadTradeEvent $event, CopyOrder $copyOrder): CopyOrder
    {
        $market = Market::query()->where('symbol', $this->normalizeSpotSymbol($event->symbol))->first();
        if (!$market || $market->status !== 'active') {
            return $this->skip($copyOrder, 'MARKET_UNAVAILABLE');
        }

        $allowed = $relationship->allowed_symbols;
        if (is_array($allowed) && $allowed !== [] && !in_array(strtoupper($event->symbol), array_map('strtoupper', $allowed), true)) {
            return $this->skip($copyOrder, 'ASSET_NOT_ALLOWED');
        }

        $side = strtolower((string) $event->side);
        if (!in_array($side, ['buy', 'sell'], true)) {
            return $this->skip($copyOrder, 'INVALID_SIDE');
        }

        $quantity = $side === 'sell'
            ? $this->spotSellQuantity($relationship, $event)
            : $this->targetQuantity($relationship, $event);

        if (FinancialDecimal::compare($quantity, '0', self::SCALE) <= 0) {
            return $this->skip($copyOrder, 'BELOW_MINIMUM_ORDER');
        }

        $limitPrice = $this->spotSlippageLimitPrice($event, $side);
        $copyOrder->target_quantity = $quantity;
        $copyOrder->save();

        $result = $this->spotOrders->placeOrder((int) $relationship->follower_id, $market->symbol, $side, 'limit', $quantity, $limitPrice, [
            'time_in_force' => 'IOC',
            'client_order_id' => 'copy:' . $copyOrder->copy_order_uuid,
            'account_type' => 'unified_trading',
            'source' => 'copy_trading',
            'copy_relationship_id' => $relationship->id,
            'lead_trade_event_id' => $event->id,
        ]);

        /** @var Order $order */
        $order = $result['order']->fresh();
        $trades = collect($result['trades'] ?? []);
        if ($trades->isEmpty() || FinancialDecimal::compare((string) $order->filled_amount, '0', self::SCALE) <= 0) {
            return $this->skip($copyOrder, 'SKIPPED_SLIPPAGE_LIMIT');
        }

        $executedQty = (string) $order->filled_amount;
        $executedNotional = $trades->reduce(
            fn (string $carry, $trade): string => FinancialDecimal::add($carry, (string) $trade->quote_amount),
            '0'
        );
        $avgPrice = FinancialDecimal::div($executedNotional, $executedQty);
        $slippage = $this->slippageBps((string) $event->execution_price, $avgPrice);

        $copyOrder->status = FinancialDecimal::compare((string) $order->remaining_amount, '0', self::SCALE) > 0 ? 'partially_filled' : 'filled';
        $copyOrder->follower_spot_order_id = $order->id;
        $copyOrder->submitted_quantity = $quantity;
        $copyOrder->executed_quantity = $executedQty;
        $copyOrder->executed_notional = $executedNotional;
        $copyOrder->follower_execution_price = $avgPrice;
        $copyOrder->copy_slippage_bps = $slippage;
        $copyOrder->submitted_at = now();
        $copyOrder->completed_at = now();
        $copyOrder->save();

        $this->upsertSpotAttribution($relationship, $event, $market, $executedQty, $executedNotional, $side);
        $this->realtime->record((int) $relationship->follower_id, 'copy.fill', [
            'copy_order_id' => $copyOrder->copy_order_uuid,
            'spot_order_id' => $order->order_uuid,
            'symbol' => $market->symbol,
            'side' => $side,
            'executed_quantity' => $executedQty,
            'executed_notional' => $executedNotional,
            'slippage_bps' => $slippage,
        ]);

        return $copyOrder->fresh(['followerSpotOrder']);
    }

    private function skip(CopyOrder $copyOrder, string $reason): CopyOrder
    {
        $copyOrder->status = 'skipped';
        $copyOrder->reason_code = $reason;
        $copyOrder->completed_at = now();
        $copyOrder->save();

        $this->realtime->record((int) $copyOrder->follower_user_id, 'copy.order', [
            'copy_order_id' => $copyOrder->copy_order_uuid,
            'status' => 'skipped',
            'reason_code' => $reason,
        ]);

        return $copyOrder->fresh();
    }

    private function upsertStrategyPosition(CopyRelationship $relationship, CopyLeadTradeEvent $event, string $quantity): void
    {
        CopyStrategyPosition::query()->updateOrCreate([
            'copy_relationship_id' => $relationship->id,
            'product' => $event->product,
            'symbol' => $event->symbol,
            'asset' => data_get($event->metadata, 'base_asset'),
            'side' => $event->side,
        ], [
            'strategy_position_uuid' => (string) Str::uuid(),
            'lead_trader_id' => $event->lead_trader_id,
            'follower_user_id' => $relationship->follower_id,
            'attributed_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'average_entry_price' => (string) $event->execution_price,
            'attributed_cost_basis' => FinancialDecimal::mul((string) $event->execution_price, $quantity),
            'sync_status' => 'synced',
            'metadata' => [
                'last_lead_trade_event_id' => $event->id,
                'position_effect' => $event->position_effect,
            ],
        ]);
    }

    private function upsertSpotAttribution(CopyRelationship $relationship, CopyLeadTradeEvent $event, Market $market, string $quantity, string $notional, string $side): void
    {
        $position = CopyStrategyPosition::query()
            ->where('copy_relationship_id', $relationship->id)
            ->where('product', 'spot')
            ->where('symbol', $market->symbol)
            ->where('side', 'long')
            ->lockForUpdate()
            ->first();

        if ($side === 'buy') {
            if (!$position) {
                CopyStrategyPosition::query()->create([
                    'strategy_position_uuid' => (string) Str::uuid(),
                    'copy_relationship_id' => $relationship->id,
                    'lead_trader_id' => $event->lead_trader_id,
                    'follower_user_id' => $relationship->follower_id,
                    'product' => 'spot',
                    'symbol' => $market->symbol,
                    'asset' => strtoupper((string) $market->base_currency),
                    'side' => 'long',
                    'attributed_quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'average_entry_price' => FinancialDecimal::div($notional, $quantity),
                    'attributed_cost_basis' => $notional,
                    'realized_pnl' => '0',
                    'fees' => '0',
                    'sync_status' => 'synced',
                    'metadata' => ['last_copy_order_uuid' => $event->event_id],
                ]);
                return;
            }

            $newQuantity = FinancialDecimal::add((string) $position->remaining_quantity, $quantity);
            $newCost = FinancialDecimal::add((string) $position->attributed_cost_basis, $notional);
            $position->attributed_quantity = FinancialDecimal::add((string) $position->attributed_quantity, $quantity);
            $position->remaining_quantity = $newQuantity;
            $position->attributed_cost_basis = $newCost;
            $position->average_entry_price = FinancialDecimal::compare($newQuantity, '0') > 0 ? FinancialDecimal::div($newCost, $newQuantity) : '0';
            $position->metadata = array_merge($position->metadata ?? [], ['last_copy_event_id' => $event->event_id]);
            $position->save();
            return;
        }

        if (!$position) {
            return;
        }

        $sellQty = FinancialDecimal::min((string) $position->remaining_quantity, $quantity);
        $avgCost = FinancialDecimal::compare((string) $position->remaining_quantity, '0') > 0
            ? FinancialDecimal::div((string) $position->attributed_cost_basis, (string) $position->remaining_quantity)
            : '0';
        $costReleased = FinancialDecimal::mul($avgCost, $sellQty);
        $proceeds = FinancialDecimal::mul(FinancialDecimal::div($notional, $quantity), $sellQty);

        $position->remaining_quantity = FinancialDecimal::sub((string) $position->remaining_quantity, $sellQty);
        $position->attributed_cost_basis = FinancialDecimal::sub((string) $position->attributed_cost_basis, $costReleased);
        $position->realized_pnl = FinancialDecimal::add((string) $position->realized_pnl, FinancialDecimal::sub($proceeds, $costReleased));
        $position->sync_status = 'synced';
        $position->metadata = array_merge($position->metadata ?? [], ['last_copy_event_id' => $event->event_id]);
        $position->save();
    }

    private function spotSellQuantity(CopyRelationship $relationship, CopyLeadTradeEvent $event): string
    {
        $position = CopyStrategyPosition::query()
            ->where('copy_relationship_id', $relationship->id)
            ->where('product', 'spot')
            ->where('symbol', $this->normalizeSpotSymbol($event->symbol))
            ->where('side', 'long')
            ->first();

        if (!$position || FinancialDecimal::compare((string) $position->remaining_quantity, '0') <= 0) {
            return '0';
        }

        $ratio = (string) data_get($event->metadata, 'lead_close_ratio', '1');
        $quantity = FinancialDecimal::mul((string) $position->remaining_quantity, $ratio, self::SCALE);

        return FinancialDecimal::min($quantity, (string) $position->remaining_quantity);
    }

    private function spotSlippageLimitPrice(CopyLeadTradeEvent $event, string $side): string
    {
        $leadPrice = (string) $event->execution_price;
        $bps = (string) data_get($event->metadata, 'max_copy_slippage_bps', config('copy_trading.max_copy_slippage_bps', '100'));
        $delta = FinancialDecimal::div(FinancialDecimal::mul($leadPrice, $bps), '10000');

        return $side === 'buy'
            ? FinancialDecimal::add($leadPrice, $delta)
            : FinancialDecimal::sub($leadPrice, $delta);
    }

    private function slippageBps(string $leadPrice, string $followerPrice): string
    {
        if (FinancialDecimal::compare($leadPrice, '0') <= 0) {
            return '0';
        }

        $diff = FinancialDecimal::sub($followerPrice, $leadPrice);
        if (str_starts_with($diff, '-')) {
            $diff = substr($diff, 1);
        }

        return FinancialDecimal::div(FinancialDecimal::mul($diff, '10000'), $leadPrice, self::SCALE);
    }

    private function normalizeSpotSymbol(string $symbol): string
    {
        $clean = strtoupper(trim($symbol));
        if (str_contains($clean, '/')) {
            return $clean;
        }
        foreach (['USDT', 'USDC', 'BTC', 'ETH', 'NGN', 'USD'] as $quote) {
            if (str_ends_with($clean, $quote) && strlen($clean) > strlen($quote)) {
                return substr($clean, 0, -strlen($quote)) . '/' . $quote;
            }
        }
        return $clean;
    }

    private function relationshipSupportsProduct(CopyRelationship $relationship, string $product): bool
    {
        $scope = strtolower((string) $relationship->product_scope);
        return $scope === 'all' || $scope === strtolower($product);
    }

    private function isStaleOpeningEvent(CopyLeadTradeEvent $event): bool
    {
        if (in_array((string) $event->position_effect, ['partial_close', 'close', 'reduce'], true)) {
            return false;
        }

        return $event->executed_at
            && $event->executed_at->diffInSeconds(now()) > (int) config('copy_trading.max_event_age_seconds', 300);
    }

    private function priorityFor(CopyLeadTradeEvent $event): int
    {
        return in_array((string) $event->position_effect, ['partial_close', 'close', 'reduce'], true) ? 10 : 100;
    }

    private function assertLeadCapacity(Trader $trader, string $newAllocation): void
    {
        if ((int) $trader->followers_count >= (int) config('copy_trading.max_followers_per_lead', 10000)) {
            throw new RuntimeException('Lead trader is closed to new followers.');
        }

        $projected = FinancialDecimal::add((string) $trader->copy_aum, $newAllocation);
        if (FinancialDecimal::compare($projected, (string) config('copy_trading.max_aum_per_lead', '10000000')) > 0) {
            throw new RuntimeException('Lead trader copy capacity has been reached.');
        }
    }

    private function reasonFromException(\Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        return match (true) {
            str_contains($message, 'insufficient') => 'INSUFFICIENT_MARGIN',
            str_contains($message, 'market') => 'MARKET_UNAVAILABLE',
            str_contains($message, 'leverage') => 'MAX_LEVERAGE',
            str_contains($message, 'reduce-only') => 'RISK_LIMIT',
            default => 'RISK_LIMIT',
        };
    }
}
