<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\Order;
use App\Models\SpotExecutionLeg;
use App\Models\SpotExternalVenueAccount;
use App\Models\SpotExternalVenueOrder;
use App\Services\FinancialDecimal;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SpotExternalLiquidityRouter
{
    public function __construct(
        private readonly SpotLiquidityPolicyService $policy,
        private readonly ExternalSpotVenue $venue,
        private readonly SettlementService $settlements,
        private readonly ReservationService $reservations,
    ) {
    }

    public function quoteExternalRemainder(Market $market, string $side, string $quantity): array
    {
        if (!$this->policy->canUseExternalFallback($market)) {
            throw new RuntimeException('External liquidity fallback is not enabled for this market.');
        }

        $venue = $this->venueFor($market);
        $book = $venue->getOrderBook(str_replace('/', '', strtoupper((string) $market->symbol)), 20);
        $levels = strtolower($side) === 'buy' ? ($book['asks'] ?? []) : ($book['bids'] ?? []);
        $remaining = FinancialDecimal::normalize($quantity);
        $filled = '0';
        $quoteAmount = '0';
        $limitPrice = null;

        foreach ($levels as $level) {
            if (FinancialDecimal::compare($remaining, '0') <= 0) {
                break;
            }

            $levelQty = FinancialDecimal::normalize((string) ($level['amount'] ?? $level['quantity'] ?? '0'));
            $price = FinancialDecimal::normalize((string) ($level['price'] ?? '0'));
            if (FinancialDecimal::compare($levelQty, '0') <= 0 || FinancialDecimal::compare($price, '0') <= 0) {
                continue;
            }

            $take = FinancialDecimal::min($remaining, $levelQty);
            $filled = FinancialDecimal::add($filled, $take);
            $quoteAmount = FinancialDecimal::add($quoteAmount, FinancialDecimal::mul($take, $price));
            $remaining = FinancialDecimal::sub($remaining, $take);
            $limitPrice = $price;
        }

        if (FinancialDecimal::compare($filled, $quantity) < 0) {
            throw new RuntimeException('INSUFFICIENT_EXTERNAL_LIQUIDITY');
        }

        $avgPrice = FinancialDecimal::div($quoteAmount, $filled);
        $this->assertVenueInventory($venue->venueCode(), strtolower($side), $market, $filled, $quoteAmount);

        return [
            'venue' => $venue->venueCode(),
            'quantity' => $filled,
            'quote_amount' => $quoteAmount,
            'avg_price' => $avgPrice,
            'limit_price' => $limitPrice ?: $avgPrice,
            'book_source' => 'external_venue',
        ];
    }

    public function executeExternalRemainder(Order $order, string $quantity): array
    {
        return DB::transaction(function () use ($order, $quantity): array {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $market = $order->market()->lockForUpdate()->firstOrFail();
            $quote = $this->quoteExternalRemainder($market, (string) $order->side, $quantity);
            $venue = $this->venueFor($market);
            $externalExecutionId = (string) Str::uuid();
            $clientOrderId = 'exa_' . str_replace('-', '', $externalExecutionId);

            $venueOrder = SpotExternalVenueOrder::query()->create([
                'external_execution_id' => $externalExecutionId,
                'order_id' => $order->id,
                'market_id' => $market->id,
                'market_symbol' => $market->symbol,
                'venue' => $quote['venue'],
                'client_order_id' => $clientOrderId,
                'side' => $order->side,
                'type' => 'IOC_LIMIT',
                'quantity' => $quote['quantity'],
                'limit_price' => $quote['limit_price'],
                'status' => 'submitted',
                'request_payload' => [
                    'symbol' => str_replace('/', '', strtoupper((string) $market->symbol)),
                    'side' => strtoupper((string) $order->side),
                    'type' => 'LIMIT',
                    'timeInForce' => 'IOC',
                    'quantity' => $quote['quantity'],
                    'price' => $quote['limit_price'],
                    'newClientOrderId' => $clientOrderId,
                ],
            ]);

            $response = $venue->placeOrder((array) $venueOrder->request_payload);
            if (($response['status'] ?? '') !== 'filled') {
                $venueOrder->update(['status' => 'execution_unknown', 'response_payload' => $response, 'last_error' => 'Venue did not return filled status.']);
                throw new RuntimeException('EXTERNAL_EXECUTION_UNKNOWN');
            }

            $executedQty = FinancialDecimal::normalize((string) ($response['executed_qty'] ?? $quote['quantity']));
            $executedPrice = FinancialDecimal::normalize((string) ($response['executed_price'] ?? $quote['avg_price']));
            $executedQuote = FinancialDecimal::mul($executedQty, $executedPrice);
            $feeBasis = strtolower((string) $order->side) === 'buy' ? $executedQty : $executedQuote;
            $feeAsset = strtolower((string) $order->side) === 'buy' ? strtoupper((string) $market->base_currency) : strtoupper((string) $market->quote_currency);
            $feeAmount = FinancialDecimal::mul($feeBasis, (string) config('trading.taker_fee', '0.002'));
            $ledgerReference = 'spot-external-fill:' . $externalExecutionId;

            $this->settlements->spotExternalFill([
                'user_id' => $order->user_id,
                'side' => $order->side,
                'base_asset' => $market->base_currency,
                'quote_asset' => $market->quote_currency,
                'base_amount' => $executedQty,
                'quote_amount' => $executedQuote,
                'fee_amount' => $feeAmount,
                'fee_asset' => $feeAsset,
                'metadata' => [
                    'product' => 'spot',
                    'engine' => 'phase2d_external_liquidity',
                    'market' => $market->symbol,
                    'order_uuid' => $order->order_uuid,
                    'venue' => $quote['venue'],
                    'external_execution_id' => $externalExecutionId,
                    'external_order_client_id' => $clientOrderId,
                ],
            ], $ledgerReference);

            if ($order->reservation_id) {
                $consumeAmount = strtolower((string) $order->side) === 'buy' ? $executedQuote : $executedQty;
                $this->reservations->consume((string) $order->reservation_id, $consumeAmount, [
                    'ledger_reference' => $ledgerReference,
                    'external_execution_id' => $externalExecutionId,
                ]);
            }

            $leg = SpotExecutionLeg::query()->create([
                'execution_leg_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'market_id' => $market->id,
                'market_symbol' => $market->symbol,
                'venue' => $quote['venue'],
                'liquidity_source' => 'EXTERNAL',
                'side' => $order->side,
                'quantity' => $executedQty,
                'price' => $executedPrice,
                'quote_amount' => $executedQuote,
                'fee_amount' => $feeAmount,
                'fee_asset' => $feeAsset,
                'external_execution_id' => $externalExecutionId,
                'ledger_reference' => $ledgerReference,
                'status' => 'settled',
                'metadata' => ['client_order_id' => $clientOrderId],
                'executed_at' => now(),
            ]);

            $venueOrder->update([
                'external_order_id' => (string) ($response['id'] ?? $clientOrderId),
                'executed_quantity' => $executedQty,
                'executed_quote_amount' => $executedQuote,
                'avg_execution_price' => $executedPrice,
                'status' => 'filled',
                'response_payload' => $response,
            ]);

            $order->filled_amount = FinancialDecimal::add((string) $order->filled_amount, $executedQty, 8);
            $order->remaining_amount = FinancialDecimal::sub((string) $order->remaining_amount, $executedQty, 8);
            $order->locked_amount = $order->side === 'buy'
                ? FinancialDecimal::sub((string) $order->locked_amount, $executedQuote, 8)
                : FinancialDecimal::sub((string) $order->locked_amount, $executedQty, 8);
            if (FinancialDecimal::compare((string) $order->remaining_amount, '0', 8) <= 0) {
                $order->remaining_amount = '0';
                $order->locked_amount = '0';
                $order->status = 'filled';
            } else {
                $order->status = 'partially_filled';
            }
            $order->metadata = array_merge($order->metadata ?? [], ['external_liquidity' => true, 'last_external_execution_id' => $externalExecutionId]);
            $order->save();

            return ['venue_order' => $venueOrder->fresh(), 'leg' => $leg->fresh(), 'quote' => $quote];
        });
    }

    private function venueFor(Market $market): ExternalSpotVenue
    {
        $venue = strtoupper((string) data_get($market->external_routing_policy, 'venue', 'BINANCE'));

        return match ($venue) {
            'BINANCE' => $this->venue,
            default => throw new RuntimeException("External venue {$venue} is not configured."),
        };
    }

    private function assertVenueInventory(string $venue, string $side, Market $market, string $baseAmount, string $quoteAmount): void
    {
        $asset = $side === 'buy' ? strtoupper((string) $market->base_currency) : strtoupper((string) $market->quote_currency);
        $required = $side === 'buy' ? $baseAmount : $quoteAmount;
        $account = SpotExternalVenueAccount::query()
            ->where('venue', strtoupper($venue))
            ->where('asset', $asset)
            ->where('status', 'active')
            ->first();

        if (!$account || FinancialDecimal::compare((string) $account->available_balance, $required) < 0) {
            throw new RuntimeException('EXTERNAL_VENUE_BALANCE_INSUFFICIENT');
        }
    }
}
