<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftCardInventory;
use App\Models\GiftcardOrder;
use App\Models\LedgerTransaction;

class GiftCardReconciliationService
{
    public function run(?string $asset = null): array
    {
        $findings = [];

        GiftcardOrder::query()
            ->where('type', 'buy')
            ->whereIn('status', ['completed', 'delivered'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$findings): void {
                foreach ($orders as $order) {
                    $settlementReference = (string) data_get($order->metadata, 'settlement_reference', 'giftcard_purchase:'.$order->id);
                    if (!LedgerTransaction::query()->where('reference', $settlementReference)->where('status', 'completed')->exists()) {
                        $findings[] = [
                            'type' => 'GIFTCARD_RECONCILIATION_BREAK',
                            'severity' => 'HIGH',
                            'reason' => 'completed_buy_order_missing_completed_ledger_settlement',
                            'order_id' => $order->id,
                            'reference' => $settlementReference,
                        ];
                    }
                }
            });

        $duplicateDeliveries = GiftCardInventory::query()
            ->whereNotNull('sold_to_user_id')
            ->get()
            ->groupBy(fn ($card) => (string) data_get($card->metadata, 'giftcard_order_id'))
            ->filter(fn ($cards, $orderId) => $orderId !== '' && $cards->count() !== $cards->unique('id')->count());

        foreach ($duplicateDeliveries as $orderId => $cards) {
            $findings[] = [
                'type' => 'GIFTCARD_RECONCILIATION_BREAK',
                'severity' => 'CRITICAL',
                'reason' => 'duplicate_inventory_delivery_detected',
                'order_id' => $orderId,
                'inventory_ids' => $cards->pluck('id')->values()->all(),
            ];
        }

        return [
            'status' => $findings === [] ? 'PASS' : 'BREAK',
            'findings' => $findings,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}

