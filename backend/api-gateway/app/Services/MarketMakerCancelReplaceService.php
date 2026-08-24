<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesOrder;
use App\Models\InstitutionalAccount;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotOrder;
use App\Models\MarketMakerBotQuoteCycle;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class MarketMakerCancelReplaceService
{
    public function __construct(
        private readonly TradeService $spot,
        private readonly FuturesOrderService $futures,
    ) {
    }

    public function reconcile(MarketMakerBot $bot, MarketMakerBotQuoteCycle $cycle, callable $submit): MarketMakerBotQuoteCycle
    {
        $institution = InstitutionalAccount::query()->findOrFail($bot->institution_id);
        $existing = MarketMakerBotOrder::query()
            ->where('bot_id', $bot->id)
            ->whereIn('status', ['SUBMITTED', 'PARTIALLY_FILLED', 'UNKNOWN_CANCEL'])
            ->lockForUpdate()
            ->get();
        $planned = collect($cycle->quote_plan ?? []);
        $submitted = [];

        foreach ($planned as $quote) {
            $match = $existing->first(fn (MarketMakerBotOrder $order): bool => $this->sameQuote($bot, $order, $quote));
            if ($match) {
                $match->forceFill(['metadata' => array_merge($match->metadata ?? [], ['kept_in_cycle' => $cycle->cycle_uuid])])->save();
                $submitted[] = ['client_order_id' => $match->client_order_id, 'status' => 'KEPT', 'bot_order_id' => $match->id];
                continue;
            }
            $submitted[] = $submit($quote);
        }

        foreach ($existing as $order) {
            $stillDesired = $planned->contains(fn (array $quote): bool => $this->sameQuote($bot, $order, $quote));
            if (! $stillDesired) {
                $this->cancelBotOrder($institution, $order);
            }
        }

        $cycle->forceFill(['status' => 'SUBMITTED', 'submitted_orders' => $submitted])->save();

        return $cycle->fresh();
    }

    public function massCancel(MarketMakerBot $bot, string $reason): array
    {
        $institution = InstitutionalAccount::query()->findOrFail($bot->institution_id);
        $orders = MarketMakerBotOrder::query()
            ->where('bot_id', $bot->id)
            ->whereIn('status', ['SUBMITTED', 'PARTIALLY_FILLED', 'UNKNOWN_CANCEL'])
            ->get();
        $cancelled = [];
        $failed = [];
        foreach ($orders as $order) {
            try {
                $cancelled[] = $this->cancelBotOrder($institution, $order, $reason)->client_order_id;
            } catch (\Throwable $exception) {
                $order->forceFill(['status' => 'UNKNOWN_CANCEL', 'metadata' => array_merge($order->metadata ?? [], ['cancel_error' => $exception->getMessage(), 'reason' => $reason])])->save();
                $failed[] = ['client_order_id' => $order->client_order_id, 'error' => $exception->getMessage()];
            }
        }

        return ['cancelled' => $cancelled, 'failed' => $failed];
    }

    private function sameQuote(MarketMakerBot $bot, MarketMakerBotOrder $order, array $quote): bool
    {
        $priceThreshold = FinancialDecimal::normalize((string) ($bot->configuration['price_change_threshold_bps'] ?? '1'), 8);
        $sizeThreshold = FinancialDecimal::normalize((string) ($bot->configuration['size_change_threshold_bps'] ?? '100'), 8);
        $price = FinancialDecimal::normalize((string) $order->price);
        $newPrice = FinancialDecimal::normalize((string) $quote['price']);
        $quantity = FinancialDecimal::normalize((string) $order->quantity);
        $newQuantity = FinancialDecimal::normalize((string) $quote['quantity']);
        $priceDiffBps = FinancialDecimal::compare($price, '0') > 0 ? FinancialDecimal::mul(FinancialDecimal::div(FinancialDecimal::abs(FinancialDecimal::sub($price, $newPrice)), $price), '10000', 8) : '999999';
        $sizeDiffBps = FinancialDecimal::compare($quantity, '0') > 0 ? FinancialDecimal::mul(FinancialDecimal::div(FinancialDecimal::abs(FinancialDecimal::sub($quantity, $newQuantity)), $quantity), '10000', 8) : '999999';

        return $order->side === $quote['side']
            && (int) ($order->metadata['level'] ?? 0) === (int) ($quote['level'] ?? 0)
            && FinancialDecimal::compare($priceDiffBps, $priceThreshold, 8) <= 0
            && FinancialDecimal::compare($sizeDiffBps, $sizeThreshold, 8) <= 0;
    }

    private function cancelBotOrder(InstitutionalAccount $institution, MarketMakerBotOrder $botOrder, string $reason = 'quote_replacement'): MarketMakerBotOrder
    {
        return DB::transaction(function () use ($botOrder, $institution, $reason): MarketMakerBotOrder {
            $locked = MarketMakerBotOrder::query()->whereKey($botOrder->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['CANCELLED', 'FILLED'], true)) {
                return $locked;
            }
            if ($locked->spot_order_id) {
                $order = Order::query()->find($locked->spot_order_id);
                if ($order && in_array($order->status, ['open', 'partially_filled', 'pending_trigger'], true)) {
                    $this->spot->cancelOrder((int) $institution->master_user_id, (string) $order->order_uuid);
                }
            }
            if ($locked->futures_order_id) {
                $order = FuturesOrder::query()->find($locked->futures_order_id);
                if ($order && in_array($order->status, ['open', 'partially_filled', 'pending_trigger'], true)) {
                    $this->futures->cancelOrder((int) $institution->master_user_id, (string) $order->order_uuid);
                }
            }
            $locked->forceFill(['status' => 'CANCELLED', 'metadata' => array_merge($locked->metadata ?? [], ['cancel_reason' => $reason, 'cancelled_at' => now()->toISOString()])])->save();

            return $locked->fresh();
        });
    }
}
