<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Market;
use App\Models\Order;
use App\Models\SpotSettlementOutbox;
use App\Models\Trade;
use App\Models\Transaction;
use App\Services\FinancialDecimal;
use App\Services\MarketStreamService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrderManagementService
{
    public function __construct(
        private readonly PreTradeValidationService $validator,
        private readonly InstrumentService $instruments,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlements,
        private readonly SpotSequencer $sequencer,
        private readonly MarketEngineLeaseService $leases,
        private readonly SpotMatchingEngine $engine,
        private readonly ExecutionJournalService $journal,
        private readonly OrderBookSnapshotService $snapshots,
        private readonly SpotRealtimeSequenceService $realtime,
        private readonly MarketStreamService $marketStream,
        private readonly SpotLiquidityPolicyService $liquidityPolicy,
        private readonly SpotExternalLiquidityRouter $externalLiquidity,
    ) {
    }

    public function placeOrder(int $userId, array $command): array
    {
        $clientOrderId = isset($command['client_order_id']) ? (string) $command['client_order_id'] : null;
        $existing = $this->validator->existingClientOrder($userId, (string) $command['pair'], $clientOrderId);
        if ($existing) {
            return [
                'order' => $existing->fresh(),
                'trades' => [],
                'order_book' => $this->compatOrderBook($existing->market),
                'idempotent' => true,
            ];
        }

        return DB::transaction(function () use ($command, $userId): array {
            $validated = $this->validator->validateNewOrder($userId, $command);
            /** @var Market $market */
            $market = $validated['market'];
            $marketPolicy = $this->liquidityPolicy->policyFor($market);
            if ($marketPolicy['liquidity_mode'] === SpotLiquidityPolicyService::DISABLED) {
                throw new RuntimeException('Spot market liquidity is disabled.');
            }
            $lease = $this->leases->acquire($market);
            $sequence = $this->sequencer->next($market);
            $this->leases->assertCurrent($market, (string) $lease->lease_token, (int) $lease->generation);
            $orderUuid = (string) Str::uuid();
            $lock = $this->reservationAmount($market, $validated);
            $accountType = (string) ($command['account_type'] ?? 'unified_trading');

            $reservation = $this->reservations->reserveUserAccount(
                $userId,
                $accountType,
                $lock['asset'],
                $lock['amount'],
                'spot_order',
                'order',
                $orderUuid,
                'spot-order:' . $orderUuid,
                [
                    'product' => 'spot',
                    'engine' => 'phase2',
                    'market' => $market->symbol,
                    'sequence' => $sequence,
                    'lease_generation' => (int) $lease->generation,
                    'lease_token' => (string) $lease->lease_token,
                    'side' => $validated['side'],
                    'type' => $validated['type'],
                    'account_type' => $accountType,
                ],
            );

            $order = Order::query()->create([
                'order_uuid' => $orderUuid,
                'client_order_id' => $validated['client_order_id'],
                'user_id' => $userId,
                'market_id' => $market->id,
                'pair' => $market->symbol,
                'side' => $validated['side'],
                'type' => $validated['type'],
                'time_in_force' => $validated['time_in_force'],
                'post_only' => $validated['post_only'],
                'price' => $validated['type'] === 'limit' ? $validated['price'] : '0',
                'amount' => $validated['quantity'],
                'filled_amount' => '0',
                'remaining_amount' => $validated['quantity'],
                'locked_amount' => $lock['amount'],
                'locked_currency' => $lock['asset'],
                'reservation_id' => $reservation->reservation_id,
                'status' => 'accepted',
                'sequence' => $sequence,
                'accepted_at' => now(),
                'metadata' => array_merge((array) ($command['metadata'] ?? []), [
                    'engine' => 'phase2',
                    'reservation_id' => $reservation->reservation_id,
                    'sequence' => $sequence,
                    'lease_generation' => (int) $lease->generation,
                    'reference_price' => $lock['reference_price'] ?? null,
                    'account_type' => $accountType,
                ]),
            ]);

            $this->journal->record($market, $sequence, 'ORDER_ACCEPTED', $this->orderPayload($order), $order);

            $resting = $this->restingOrders($market, $order);
            $result = $this->engine->match($order, $resting, [
                'market_protection_bps' => config('trading.market_order_protection_bps', '500'),
            ]);

            if ($result['action'] === 'reject') {
                $this->rejectOrder($order, (string) $result['reject_reason'], $sequence);

                return ['order' => $order->fresh(), 'trades' => [], 'order_book' => $this->compatOrderBook($market)];
            }

            $trades = $this->applyFills($market, $order, $result['fills'], $sequence);
            $externalExecutions = [];
            $freshOrder = $order->fresh();
            if (
                $freshOrder
                && $validated['type'] === 'market'
                && $result['action'] === 'cancel_remainder'
                && FinancialDecimal::compare((string) $freshOrder->remaining_amount, '0', 8) > 0
                && $this->liquidityPolicy->canUseExternalFallback($market)
            ) {
                $externalExecutions[] = $this->externalLiquidity->executeExternalRemainder($freshOrder, (string) $freshOrder->remaining_amount);
                $freshOrder = $freshOrder->fresh();
                if ($freshOrder && FinancialDecimal::compare((string) $freshOrder->remaining_amount, '0', 8) <= 0) {
                    $result['action'] = 'filled';
                    $result['remaining'] = '0';
                }
            }

            $this->finalizeIncomingOrder($freshOrder ?: $order->fresh(), $result, $sequence);
            $snapshot = $this->snapshots->create($market->fresh(), $sequence);
            $this->realtime->record($market, $sequence, 'BOOK_DELTA', ['bids' => $snapshot->bids, 'asks' => $snapshot->asks]);
            $this->realtime->record($market, $sequence, 'BEST_BID_ASK', [
                'best_bid' => $snapshot->bids[0] ?? null,
                'best_ask' => $snapshot->asks[0] ?? null,
            ]);
            $this->publishBook($market, $snapshot->bids, $snapshot->asks, $sequence);

            return [
                'order' => $order->fresh(),
                'trades' => $trades,
                'external_executions' => $externalExecutions,
                'order_book' => $this->compatOrderBook($market),
            ];
        });
    }

    public function cancelOrder(int $userId, string $orderUuid): Order
    {
        return DB::transaction(function () use ($orderUuid, $userId): Order {
            $order = Order::query()->where('order_uuid', $orderUuid)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') {
                return $order;
            }
            if (!in_array($order->status, ['open', 'partially_filled', 'accepted'], true)) {
                throw new RuntimeException('Only open orders can be cancelled.');
            }

            $market = $order->market()->lockForUpdate()->firstOrFail();
            $lease = $this->leases->acquire($market);
            $sequence = $this->sequencer->next($market);
            $this->leases->assertCurrent($market, (string) $lease->lease_token, (int) $lease->generation);
            $reservationId = (string) ($order->reservation_id ?: data_get($order->metadata, 'reservation_id', ''));
            if ($reservationId !== '' && FinancialDecimal::compare((string) $order->locked_amount, '0') > 0) {
                $this->reservations->release($reservationId, null, ['event' => 'phase2_order_cancel', 'order_uuid' => $order->order_uuid]);
            }

            $order->status = 'cancelled';
            $order->locked_amount = '0';
            $order->cancelled_at = now();
            $order->metadata = array_merge($order->metadata ?? [], ['cancel_sequence' => $sequence]);
            $order->save();

            $this->journal->record($market, $sequence, 'ORDER_CANCELLED', $this->orderPayload($order), $order);
            $snapshot = $this->snapshots->create($market, $sequence);
            $this->realtime->record($market, $sequence, 'ORDER_REMOVED', ['order_uuid' => $order->order_uuid, 'reason' => 'cancelled']);
            $this->realtime->record($market, $sequence, 'BOOK_DELTA', ['bids' => $snapshot->bids, 'asks' => $snapshot->asks]);
            $this->publishBook($market, $snapshot->bids, $snapshot->asks, $sequence);

            return $order->fresh();
        });
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{asset:string, amount:string, reference_price?:string}
     */
    private function reservationAmount(Market $market, array $validated): array
    {
        if ($validated['side'] === 'sell') {
            return ['asset' => strtoupper((string) $market->base_currency), 'amount' => $validated['quantity']];
        }

        if ($validated['type'] === 'limit') {
            return [
                'asset' => strtoupper((string) $market->quote_currency),
                'amount' => $this->instruments->quoteAmount($validated['quantity'], $validated['price']),
            ];
        }

        $required = $this->quoteRequiredForMarketBuy($market, $validated['quantity']);
        $slippageBps = FinancialDecimal::normalize((string) config('trading.market_order_slippage_bps', '100'));
        $buffer = FinancialDecimal::div(FinancialDecimal::mul($required, $slippageBps), '10000');

        return [
            'asset' => strtoupper((string) $market->quote_currency),
            'amount' => FinancialDecimal::add($required, $buffer),
            'reference_price' => $this->bestAsk($market) ?? '0',
        ];
    }

    private function quoteRequiredForMarketBuy(Market $market, string $quantity): string
    {
        $remaining = FinancialDecimal::normalize($quantity);
        $quote = '0';
        foreach ($this->restingOrdersForSide($market, 'sell') as $order) {
            if (FinancialDecimal::compare($remaining, '0') <= 0) {
                break;
            }
            $take = FinancialDecimal::min($remaining, (string) $order->remaining_amount);
            $quote = FinancialDecimal::add($quote, FinancialDecimal::mul($take, (string) $order->price));
            $remaining = FinancialDecimal::sub($remaining, $take);
        }

        if (FinancialDecimal::compare($remaining, '0') > 0) {
            if ($this->liquidityPolicy->canUseExternalFallback($market)) {
                $external = $this->externalLiquidity->quoteExternalRemainder($market, 'buy', $remaining);
                return FinancialDecimal::add($quote, (string) $external['quote_amount']);
            }

            throw new RuntimeException('No liquidity available for market order.');
        }

        return $quote;
    }

    /**
     * @return array<int, Order>
     */
    private function restingOrders(Market $market, Order $incoming): array
    {
        return $this->restingOrdersForSide($market, $incoming->side === 'buy' ? 'sell' : 'buy', $incoming->id);
    }

    /**
     * @return array<int, Order>
     */
    private function restingOrdersForSide(Market $market, string $side, ?int $excludeOrderId = null): array
    {
        return Order::query()
            ->where('market_id', $market->id)
            ->where('side', $side)
            ->whereIn('status', ['open', 'partially_filled'])
            ->when($excludeOrderId, fn ($query) => $query->where('id', '!=', $excludeOrderId))
            ->lockForUpdate()
            ->get()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $fills
     * @return array<int, Trade>
     */
    private function applyFills(Market $market, Order $incoming, array $fills, int $sequence): array
    {
        $trades = [];
        foreach ($fills as $fill) {
            $maker = Order::query()->lockForUpdate()->findOrFail((int) $fill['maker_order_id']);
            $taker = Order::query()->lockForUpdate()->findOrFail((int) $fill['taker_order_id']);
            $buyOrder = $maker->side === 'buy' ? $maker : $taker;
            $sellOrder = $maker->side === 'sell' ? $maker : $taker;
            $quantity = (string) $fill['quantity'];
            $price = (string) $fill['price'];
            $quoteAmount = (string) $fill['quote_amount'];
            $executionId = (string) Str::uuid();

            $makerFeeBasis = $maker->side === 'buy' ? $quantity : $quoteAmount;
            $takerFeeBasis = $taker->side === 'buy' ? $quantity : $quoteAmount;
            $makerFee = FinancialDecimal::mul($makerFeeBasis, (string) config('trading.maker_fee', '0.001'), 18);
            $takerFee = FinancialDecimal::mul($takerFeeBasis, (string) config('trading.taker_fee', '0.002'), 18);
            $buyerFee = $buyOrder->id === $maker->id ? $makerFee : $takerFee;
            $sellerFee = $sellOrder->id === $maker->id ? $makerFee : $takerFee;

            $this->decrementOrder($maker, $quantity, $quoteAmount, $price);
            $this->decrementOrder($taker, $quantity, $quoteAmount, $price);

            $trade = Trade::query()->create([
                'trade_uuid' => (string) Str::uuid(),
                'market_id' => $market->id,
                'buy_order_id' => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'maker_order_id' => $maker->id,
                'taker_order_id' => $taker->id,
                'pair' => $market->symbol,
                'sequence' => $sequence,
                'price' => $price,
                'amount' => $quantity,
                'quote_amount' => $quoteAmount,
                'maker_fee' => $makerFee,
                'taker_fee' => $takerFee,
                'settlement_status' => 'pending',
                'settlement_reference' => 'spot-fill:' . $executionId,
                'executed_at' => now(),
                'metadata' => [
                    'execution_id' => $executionId,
                    'maker_order_uuid' => $maker->order_uuid,
                    'taker_order_uuid' => $taker->order_uuid,
                    'maker_side' => $maker->side,
                    'taker_side' => $taker->side,
                    'buyer_fee' => $buyerFee,
                    'buyer_fee_currency' => $market->base_currency,
                    'buyer_net_base' => FinancialDecimal::sub($quantity, $buyerFee),
                    'seller_fee' => $sellerFee,
                    'seller_fee_currency' => $market->quote_currency,
                    'seller_net_quote' => FinancialDecimal::sub($quoteAmount, $sellerFee),
                ],
            ]);

            $payload = $this->settlementPayload($market, $buyOrder->fresh(), $sellOrder->fresh(), $trade->fresh(), $buyerFee, $sellerFee);
            $outbox = SpotSettlementOutbox::query()->firstOrCreate([
                'reference' => (string) $trade->settlement_reference,
            ], [
                'outbox_id' => (string) Str::uuid(),
                'execution_id' => $executionId,
                'trade_id' => $trade->id,
                'status' => 'pending',
                'payload' => $payload,
            ]);

            try {
                $this->settlements->spotTrade($payload, (string) $trade->settlement_reference);
                $marginOrders = app(\App\Services\MarginOrderService::class);
                $marginOrders->syncAutoRepayForSpotOrder($buyOrder->fresh());
                $marginOrders->syncAutoRepayForSpotOrder($sellOrder->fresh());
                $marginOrders->syncAutoBorrowForSpotOrder($buyOrder->fresh());
                $marginOrders->syncAutoBorrowForSpotOrder($sellOrder->fresh());
                $trade->settlement_status = 'settled';
                $trade->save();
                $outbox->status = 'settled';
                $outbox->settled_at = now();
                $outbox->attempts = ((int) $outbox->attempts) + 1;
                $outbox->save();
            } catch (Throwable $exception) {
                $trade->settlement_status = 'failed_retryable';
                $trade->save();
                $outbox->status = 'failed_retryable';
                $outbox->last_error = $exception->getMessage();
                $outbox->attempts = ((int) $outbox->attempts) + 1;
                $outbox->save();
                throw $exception;
            }

            $this->journal->record($market, $sequence, 'TRADE_EXECUTED', [
                'execution_id' => $executionId,
                'trade_uuid' => $trade->trade_uuid,
                'price' => $price,
                'quantity' => $quantity,
                'quote_amount' => $quoteAmount,
                'maker_order_uuid' => $maker->order_uuid,
                'taker_order_uuid' => $taker->order_uuid,
            ], $incoming, $executionId);
            $this->realtime->record($market, $sequence, 'TRADE', [
                'execution_id' => $executionId,
                'trade_uuid' => $trade->trade_uuid,
                'price' => $price,
                'quantity' => $quantity,
                'quote_amount' => $quoteAmount,
            ]);

            $market->last_price = $price;
            $market->save();
            $this->recordCompatibilityTransactions($market, $buyOrder->fresh(), $sellOrder->fresh(), $trade->fresh());
            $trades[] = $trade->fresh();
        }

        return $trades;
    }

    private function decrementOrder(Order $order, string $quantity, string $quoteAmount, string $executionPrice): void
    {
        $lockedReduction = $order->side === 'sell'
            ? $quantity
            : ($order->type === 'limit' ? FinancialDecimal::mul($quantity, (string) $order->price) : $quoteAmount);
        $refund = '0';
        if ($order->side === 'buy' && $order->type === 'limit') {
            $refund = FinancialDecimal::sub($lockedReduction, $quoteAmount);
        }

        $order->filled_amount = FinancialDecimal::add((string) $order->filled_amount, $quantity);
        $order->remaining_amount = FinancialDecimal::sub((string) $order->remaining_amount, $quantity);
        $order->locked_amount = FinancialDecimal::sub((string) $order->locked_amount, $lockedReduction);
        $order->status = FinancialDecimal::compare((string) $order->remaining_amount, '0') <= 0 ? 'filled' : 'partially_filled';
        $order->save();

        if (FinancialDecimal::compare($refund, '0') > 0 && $order->reservation_id) {
            $this->reservations->release((string) $order->reservation_id, $refund, [
                'event' => 'phase2_price_improvement',
                'order_uuid' => $order->order_uuid,
                'execution_price' => $executionPrice,
            ]);
        }
    }

    private function finalizeIncomingOrder(Order $order, array $result, int $sequence): void
    {
        $order->refresh();
        if ($result['action'] === 'rest') {
            $order->status = FinancialDecimal::compare((string) $order->filled_amount, '0') > 0 ? 'partially_filled' : 'open';
            $order->opened_at = $order->opened_at ?? now();
            $order->save();
            $this->journal->record($order->market, $sequence, $order->status === 'open' ? 'ORDER_OPENED' : 'ORDER_PARTIALLY_FILLED', $this->orderPayload($order), $order);
            return;
        }

        if ($result['action'] === 'filled') {
            if ($order->reservation_id && FinancialDecimal::compare((string) $order->locked_amount, '0') > 0) {
                $this->reservations->release((string) $order->reservation_id, null, [
                    'event' => 'phase2_filled_order_surplus_release',
                    'order_uuid' => $order->order_uuid,
                ]);
            }
            $order->status = 'filled';
            $order->remaining_amount = '0';
            $order->locked_amount = '0';
            $order->save();
            $this->journal->record($order->market, $sequence, 'ORDER_FILLED', $this->orderPayload($order), $order);
            return;
        }

        if ($order->reservation_id && FinancialDecimal::compare((string) $order->locked_amount, '0') > 0) {
            $this->reservations->release((string) $order->reservation_id, null, [
                'event' => 'phase2_remainder_release',
                'order_uuid' => $order->order_uuid,
                'reason' => $result['reject_reason'] ?? $result['action'],
            ]);
        }
        $order->status = FinancialDecimal::compare((string) $order->filled_amount, '0') > 0 ? 'cancelled' : 'cancelled';
        $order->locked_amount = '0';
        $order->metadata = array_merge($order->metadata ?? [], ['terminal_reason' => $result['reject_reason'] ?? $result['action']]);
        $order->cancelled_at = now();
        $order->save();
        $this->journal->record($order->market, $sequence, 'ORDER_CANCELLED', $this->orderPayload($order), $order);
    }

    private function rejectOrder(Order $order, string $reason, int $sequence): void
    {
        if ($order->reservation_id) {
            $this->reservations->release((string) $order->reservation_id, null, ['event' => 'phase2_order_reject', 'order_uuid' => $order->order_uuid]);
        }
        $order->status = 'rejected';
        $order->locked_amount = '0';
        $order->metadata = array_merge($order->metadata ?? [], ['reject_reason' => $reason]);
        $order->save();
        $this->journal->record($order->market, $sequence, 'ORDER_REJECTED', $this->orderPayload($order), $order);
    }

    private function settlementPayload(Market $market, Order $buyOrder, Order $sellOrder, Trade $trade, string $buyerFee, string $sellerFee): array
    {
        return [
            'buyer_user_id' => $buyOrder->user_id,
            'seller_user_id' => $sellOrder->user_id,
            'base_asset' => $market->base_currency,
            'quote_asset' => $market->quote_currency,
            'base_amount' => (string) $trade->amount,
            'quote_amount' => (string) $trade->quote_amount,
            'buyer_fee' => $buyerFee,
            'seller_fee' => $sellerFee,
            'buyer_account_type' => data_get($buyOrder->metadata, 'account_type', 'unified_trading'),
            'seller_account_type' => data_get($sellOrder->metadata, 'account_type', 'unified_trading'),
            'consume_reservations' => [
                (string) $buyOrder->reservation_id => (string) $trade->quote_amount,
                (string) $sellOrder->reservation_id => (string) $trade->amount,
            ],
            'metadata' => [
                'product' => 'spot',
                'engine' => 'phase2',
                'market' => $market->symbol,
                'execution_reference' => data_get($trade->metadata, 'execution_id'),
                'trade_uuid' => $trade->trade_uuid,
                'buy_order_reference' => $buyOrder->order_uuid,
                'sell_order_reference' => $sellOrder->order_uuid,
                'maker_order_reference' => data_get($trade->metadata, 'maker_order_uuid'),
                'taker_order_reference' => data_get($trade->metadata, 'taker_order_uuid'),
                'maker_fee_rate' => (string) config('trading.maker_fee', '0.001'),
                'taker_fee_rate' => (string) config('trading.taker_fee', '0.002'),
            ],
        ];
    }

    private function recordCompatibilityTransactions(Market $market, Order $buyOrder, Order $sellOrder, Trade $trade): void
    {
        Transaction::query()->firstOrCreate([
            'reference' => $buyOrder->order_uuid,
            'tx_hash' => 'phase2-buy-' . $trade->trade_uuid,
        ], [
            'transaction_id' => strtoupper((string) Str::uuid()),
            'user_id' => $buyOrder->user_id,
            'type' => TransactionType::Trade,
            'currency' => $market->base_currency,
            'amount' => (string) data_get($trade->metadata, 'buyer_net_base', '0'),
            'fee' => (string) data_get($trade->metadata, 'buyer_fee', '0'),
            'status' => TransactionStatus::Completed,
            'metadata' => ['trade_uuid' => $trade->trade_uuid, 'pair' => $market->symbol, 'side' => 'buy', 'engine' => 'phase2'],
        ]);

        Transaction::query()->firstOrCreate([
            'reference' => $sellOrder->order_uuid,
            'tx_hash' => 'phase2-sell-' . $trade->trade_uuid,
        ], [
            'transaction_id' => strtoupper((string) Str::uuid()),
            'user_id' => $sellOrder->user_id,
            'type' => TransactionType::Trade,
            'currency' => $market->quote_currency,
            'amount' => (string) data_get($trade->metadata, 'seller_net_quote', '0'),
            'fee' => (string) data_get($trade->metadata, 'seller_fee', '0'),
            'status' => TransactionStatus::Completed,
            'metadata' => ['trade_uuid' => $trade->trade_uuid, 'pair' => $market->symbol, 'side' => 'sell', 'engine' => 'phase2'],
        ]);
    }

    private function orderPayload(Order $order): array
    {
        return [
            'order_uuid' => $order->order_uuid,
            'client_order_id' => $order->client_order_id,
            'user_id' => $order->user_id,
            'pair' => $order->pair,
            'side' => $order->side,
            'type' => $order->type,
            'time_in_force' => $order->time_in_force,
            'post_only' => $order->post_only,
            'price' => (string) $order->price,
            'amount' => (string) $order->amount,
            'filled_amount' => (string) $order->filled_amount,
            'remaining_amount' => (string) $order->remaining_amount,
            'status' => $order->status,
            'sequence' => (int) $order->sequence,
        ];
    }

    private function bestAsk(Market $market): ?string
    {
        $order = Order::query()
            ->where('market_id', $market->id)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price')
            ->orderBy('sequence')
            ->first();

        return $order ? (string) $order->price : null;
    }

    private function compatOrderBook(Market $market): array
    {
        $snapshot = $this->snapshots->latest($market);
        return [
            'pair' => $market->symbol,
            'bids' => $snapshot?->bids ?? [],
            'asks' => $snapshot?->asks ?? [],
            'last_synced_at' => now()->toISOString(),
            'sequence' => $snapshot?->last_sequence,
        ];
    }

    private function publishBook(Market $market, array $bids, array $asks, int $sequence): void
    {
        $this->marketStream->publish([
            'type' => 'order_book',
            'pair' => $market->symbol,
            'sequence' => $sequence,
            'data' => ['bids' => $bids, 'asks' => $asks, 'timestamp' => now()->toISOString()],
        ]);
    }
}
