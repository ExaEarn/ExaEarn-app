<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftcardOrder;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\ReservationService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GiftCardPurchaseService
{
    private const SCALE = 8;

    public function __construct(
        private readonly GiftCardFeeCalculator $feeCalculator,
        private readonly LedgerService $ledgerService,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlement,
        private readonly GiftCardProviderManager $providers,
    ) {
    }

    /**
     * Complete gift card purchase flow:
     * 1. Calculate fees and total cost
     * 2. Verify user has sufficient balance
     * 3. Deduct from user wallet
     * 4. Create order with fee tracking
     * 5. Call external API to purchase card
     * 6. Record ledger entries
     * 7. Track platform profit
     */
    public function purchaseGiftCard(
        User $user,
        string $brand,
        int|float|string $cardValue,
        string $deliveryEmail,
        string $currency = 'USD',
        string $walletType = 'funding',
        array $metadata = []
    ): GiftcardOrder {
        return DB::transaction(function () use ($user, $brand, $cardValue, $deliveryEmail, $currency, $walletType, $metadata) {
            $cardValue = FinancialDecimal::normalize((string) $cardValue, self::SCALE);
            $asset = strtoupper($currency);
            // 1. Calculate all fees
            $feeBreakdown = $this->feeCalculator->calculateFees($brand, $cardValue, $currency);
            $totalCost = FinancialDecimal::normalize((string) $feeBreakdown['total_cost_to_user'], self::SCALE);
            $userCharge = FinancialDecimal::normalize((string) $feeBreakdown['user_charge'], self::SCALE);

            // 2. Reserve user funds through canonical reservation service.
            $reservation = $this->reservations->reserveUserAccount(
                $user->id,
                $walletType,
                $asset,
                $totalCost,
                'giftcard_purchase',
                'giftcard_order',
                null,
                'giftcard-purchase:'.$user->id.':'.$brand.':'.$deliveryEmail.':'.$cardValue.':'.now()->format('YmdHis'),
                ['brand' => $brand, 'delivery_email' => $deliveryEmail],
            );

            // 3. Create order record with fee details
            $order = $this->createGiftcardOrder($user, $brand, $feeBreakdown, $walletType, $asset, $metadata + [
                'reservation_id' => $reservation->reservation_id,
                'pricing_snapshot' => $this->pricingSnapshot($feeBreakdown, $asset),
                'delivery_email' => $deliveryEmail,
            ]);

            $providerResult = $this->providers->provider((string) ($metadata['provider'] ?? null))->purchase([
                'brand' => $brand,
                'card_value' => $cardValue,
                'currency' => $asset,
                'delivery_email' => $deliveryEmail,
                'idempotency_key' => 'provider:giftcard:'.$order->reference,
                'scenario' => $metadata['provider_scenario'] ?? null,
            ]);

            if (in_array($providerResult['status'] ?? null, ['FAILED', 'OUT_OF_STOCK'], true)) {
                $this->reservations->release((string) $reservation->reservation_id, null, ['provider_result' => $providerResult]);
                $order->update([
                    'status' => 'failed',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'provider_result' => $providerResult,
                        'reservation_status' => 'released',
                    ]),
                ]);

                throw new RuntimeException((string) ($providerResult['reason'] ?? 'Gift card provider could not fulfill this order.'));
            }

            if (($providerResult['status'] ?? null) === 'PROVIDER_UNKNOWN') {
                $order->update([
                    'status' => 'provider_unknown',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'provider_result' => $providerResult,
                        'provider_reference' => $providerResult['provider_reference'] ?? null,
                        'reservation_id' => $reservation->reservation_id,
                    ]),
                ]);

                return $order->fresh();
            }

            $settlementReference = 'giftcard_purchase:'.$order->id;
            $this->settlement->giftcardPurchaseSettle(
                (string) $reservation->reservation_id,
                $settlementReference,
                $cardValue,
                $userCharge,
                [
                    'giftcard_order_id' => $order->id,
                    'provider_reference' => $providerResult['provider_reference'] ?? null,
                    'pricing_snapshot' => $this->pricingSnapshot($feeBreakdown, $asset),
                ],
            );

            $order->update([
                'status' => 'completed',
                'delivered_at' => now(),
                'metadata' => array_merge($order->metadata ?? [], [
                    'provider_result' => $providerResult,
                    'provider_reference' => $providerResult['provider_reference'] ?? null,
                    'settlement_reference' => $settlementReference,
                    'settlement_path' => 'canonical_ledger',
                    'ledger_recorded' => true,
                    'delivery_state' => 'DELIVERED',
                ]),
            ]);

            return $order->fresh();
        });
    }

    /**
     * Create giftcard order with fee tracking.
     */
    private function createGiftcardOrder(
        User $user,
        string $brand,
        array $feeBreakdown,
        string $walletType,
        string $currency,
        array $metadata
    ): GiftcardOrder {
        return GiftcardOrder::create([
            'user_id' => $user->id,
            'type' => 'buy',
            'amount' => (string) $feeBreakdown['total_cost_to_user'],
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'payment_method' => $walletType,
            'reference' => $this->generateReference('gcp'),
            'metadata' => array_merge($metadata, [
                'brand' => $brand,
                'card_value' => $feeBreakdown['card_value'],
                'api_fee' => $feeBreakdown['api_fee'],
                'delivery_fee' => $feeBreakdown['delivery_fee'],
                'user_charged_fees' => $feeBreakdown['user_charge'],
                'platform_profit' => $feeBreakdown['platform_profit'],
                'fee_breakdown' => $feeBreakdown['fee_breakdown'],
                'total_cost' => $feeBreakdown['total_cost_to_user'],
            ]),
        ]);
    }

    public function refundPurchase(int $orderId, string $reason = 'user_request'): GiftcardOrder
    {
        return DB::transaction(function () use ($orderId, $reason) {
            $order = GiftcardOrder::lockForUpdate()->findOrFail($orderId);

            if ($order->status === 'refunded') {
                return $order;
            }

            $refundAmount = FinancialDecimal::normalize((string) $order->amount, self::SCALE);
            $refundReference = "giftcard_refund:{$orderId}";
            $this->settlement->giftcardRefundCredit(
                (int) $order->user_id,
                (string) $order->currency,
                $refundAmount,
                $refundReference,
                ['giftcard_order_id' => $order->id, 'reason' => $reason],
            );

            $order->update([
                'status' => 'refunded',
                'metadata' => array_merge($order->metadata ?? [], [
                    'refund_reason' => $reason,
                    'refunded_at' => now(),
                    'refund_reference' => $refundReference,
                ]),
            ]);

            return $order->fresh();
        });
    }

    /**
     * Generate unique reference.
     */
    private function generateReference(string $prefix): string
    {
        return "{$prefix}-" . strtoupper(\Illuminate\Support\Str::random(8)) . '-' . now()->timestamp;
    }

    private function pricingSnapshot(array $feeBreakdown, string $asset): array
    {
        return [
            'pricing_rule_id' => $feeBreakdown['pricing_rule_id'] ?? null,
            'rule_version' => $feeBreakdown['rule_version'] ?? null,
            'gross_amount' => (string) $feeBreakdown['card_value'],
            'provider_cost' => (string) $feeBreakdown['card_value'],
            'exaearn_fee' => (string) $feeBreakdown['user_charge'],
            'currency' => strtoupper($asset),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get purchase summary for user.
     */
    public function getUserPurchaseSummary(User $user, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $purchases = GiftcardOrder::where('user_id', $user->id)
            ->where('type', 'buy')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'user_id' => $user->id,
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'total_spent' => $purchases->sum('amount'),
            'purchase_count' => $purchases->count(),
            'by_brand' => $purchases->groupBy(fn ($p) => data_get($p->metadata, 'brand', 'unknown'))
                ->map(fn ($items) => [
                    'count' => $items->count(),
                    'total' => $items->sum('amount'),
                ]),
            'by_currency' => $purchases->groupBy('currency')
                ->map(fn ($items) => [
                    'count' => $items->count(),
                    'total' => $items->sum('amount'),
                ]),
        ];
    }
}
