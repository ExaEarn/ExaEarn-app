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
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gift Card Buy Service
 *
 * Core service for gift card purchasing with wallet integration,
 * fraud detection, inventory management, and ledger tracking.
 */
class GiftCardBuyService
{
    public function __construct(
        private readonly GiftCardPricingEngine $pricingEngine,
        private readonly GiftCardInventoryService $inventoryService,
        private readonly GiftCardBuyFraudDetectionService $fraudDetection,
        private readonly GiftCardDeliveryService $deliveryService,
        private readonly LedgerService $ledgerService,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlement,
    ) {
    }

    /**
     * Purchase gift cards.
     *
     * @param User $user
     * @param string $brand
     * @param float $cardValue
     * @param int $quantity
     * @param string $currency
     * @param string $paymentWalletCurrency
     * @return array
     * @throws RuntimeException
     */
    public function purchaseCards(
        User $user,
        string $brand,
        int|float|string $cardValue,
        int $quantity,
        string $currency = 'USD',
        string $paymentWalletCurrency = 'USD'
    ): array {
        $cardValue = FinancialDecimal::normalize((string) $cardValue, 8);
        $paymentWalletCurrency = strtoupper($paymentWalletCurrency);
        // 1. Validate request
        if ($quantity < 1 || $quantity > 100) {
            throw new RuntimeException('Quantity must be between 1 and 100');
        }

        // 2. Check inventory availability
        $availability = $this->inventoryService->checkAvailability($brand, (float) $cardValue, $quantity);
        if (!$availability['available']) {
            throw new RuntimeException(
                "Insufficient inventory. Required: {$quantity}, Available: {$availability['count']}"
            );
        }

        // 3. Calculate pricing
        $pricing = $this->pricingEngine->calculateTotalPrice($brand, $cardValue, $quantity, $currency, [
            'user_id' => $user->id,
            'provider' => strtolower($brand),
        ]);
        $totalPayment = FinancialDecimal::normalize((string) $pricing['total'], 8);
        $subtotal = FinancialDecimal::normalize((string) $pricing['subtotal'], 8);
        $platformFee = FinancialDecimal::normalize((string) $pricing['platform_fee'], 8);

        // 4. Check canonical funding balance
        if (!$this->ledgerService->hasBalance($user->id, $totalPayment, $paymentWalletCurrency, 'funding')) {
            throw new RuntimeException("Insufficient wallet balance. Required: {$totalPayment}");
        }

        // 5. Perform fraud analysis
        $fraudAnalysis = $this->fraudDetection->analyzeRisk($user, $brand, (float) $totalPayment, $quantity);

        // 6. Handle auto-reject immediately
        if ($fraudAnalysis['auto_decision'] === 'reject') {
            Log::warning('Gift card purchase auto-rejected', [
                'user_id' => $user->id,
                'brand' => $brand,
                'quantity' => $quantity,
                'risk_score' => $fraudAnalysis['risk_score'],
                'risk_level' => $fraudAnalysis['risk_level'],
            ]);

            return [
                'success' => false,
                'message' => 'Purchase declined due to fraud risk detection',
                'order_id' => null,
                'status' => 'rejected',
                'fraud_score' => $fraudAnalysis['risk_score'],
                'risk_level' => $fraudAnalysis['risk_level'],
            ];
        }

        // 7. Reserve cards
        try {
            $reservedCards = $this->inventoryService->reserveCards($brand, $cardValue, $quantity);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to reserve inventory: {$e->getMessage()}");
        }

        // 8. Process transaction atomically
        try {
            $result = DB::transaction(function () use (
                $user,
                $brand,
                $cardValue,
                $quantity,
                $currency,
                $paymentWalletCurrency,
                $pricing,
                $totalPayment,
                $subtotal,
                $platformFee,
                $fraudAnalysis,
                $reservedCards
            ) {
                $reservation = $this->reservations->reserveUserAccount(
                    $user->id,
                    'funding',
                    $paymentWalletCurrency,
                    $totalPayment,
                    'giftcard_purchase',
                    'giftcard_buy_order',
                    null,
                    'giftcard-buy:'.$user->id.':'.$brand.':'.uniqid('', true),
                    ['brand' => $brand, 'quantity' => $quantity],
                );

                // 8a. Create order
                $order = GiftcardOrder::create([
                    'user_id' => $user->id,
                    'type' => 'buy',
                    'brand' => $brand,
                    'amount' => $totalPayment,
                    'currency' => $paymentWalletCurrency,
                    'status' => $fraudAnalysis['auto_decision'] === 'approve' ? 'paid' : 'pending_review',
                    'risk_level' => $fraudAnalysis['risk_level'],
                    'risk_score' => $fraudAnalysis['risk_score'],
                    'requires_admin_review' => $fraudAnalysis['requires_review'],
                    'processed_at' => $fraudAnalysis['auto_decision'] === 'approve' ? now() : null,
                    'reference' => "GIFTCARD-BUY-{$user->id}-" . uniqid(),
                    'metadata' => [
                        'brand' => $brand,
                        'card_value' => $cardValue,
                        'quantity' => $quantity,
                        'unit_price' => $pricing['unit_price'],
                        'subtotal' => $pricing['subtotal'],
                        'platform_fee' => $pricing['platform_fee'],
                        'fraud_risk_score' => $fraudAnalysis['risk_score'],
                        'fraud_risk_level' => $fraudAnalysis['risk_level'],
                        'fraud_flags' => $fraudAnalysis['flags'],
                        'auto_decision' => $fraudAnalysis['auto_decision'],
                        'card_ids' => $reservedCards->pluck('id')->toArray(),
                        'payment_wallet_currency' => $paymentWalletCurrency,
                        'reservation_id' => $reservation->reservation_id,
                        'pricing_snapshot' => [
                            'gross_amount' => $subtotal,
                            'provider_cost' => $subtotal,
                            'exaearn_fee' => $platformFee,
                            'currency' => $paymentWalletCurrency,
                            'central_pricing' => $pricing['pricing_snapshot'] ?? null,
                            'timestamp' => now()->toIso8601String(),
                        ],
                    ],
                ]);

                // 8b. Fulfill inventory for auto-approved orders after canonical settlement.
                if ($fraudAnalysis['auto_decision'] === 'approve') {
                    $settlementReference = 'giftcard_purchase:'.$order->id;
                    $this->settlement->giftcardPurchaseSettle(
                        (string) $reservation->reservation_id,
                        $settlementReference,
                        $subtotal,
                        $platformFee,
                        ['giftcard_order_id' => $order->id, 'brand' => $brand, 'quantity' => $quantity],
                    );

                    $this->inventoryService->fulfillCards(
                        $reservedCards,
                        $user->id,
                        (string) $order->id
                    );

                    // Deliver cards
                    $deliverableCards = $this->deliveryService->prepareDelivery(
                        $order,
                        $reservedCards->pluck('id')->toArray()
                    );

                    $this->deliveryService->completeDelivery(
                        $order,
                        $reservedCards->pluck('id')->toArray()
                    );

                    $order->metadata = array_merge($order->metadata ?? [], [
                        'settlement_reference' => $settlementReference,
                        'settlement_path' => 'canonical_ledger',
                        'delivery_state' => 'DELIVERED',
                    ]);
                    $order->save();

                    return [
                        'order_id' => $order->id,
                        'status' => 'delivered',
                        'auto_decision' => 'approve',
                        'cards' => $this->deliveryService->getInAppDelivery($order, $deliverableCards),
                    ];
                }

                return [
                    'order_id' => $order->id,
                    'status' => 'pending_review',
                    'auto_decision' => 'review',
                    'message' => 'Purchase pending admin review',
                ];
            });

            Log::info('Gift card purchase successful', [
                'order_id' => $result['order_id'],
                'user_id' => $user->id,
                'brand' => $brand,
                'quantity' => $quantity,
                'total_amount' => $totalPayment,
                'auto_decision' => $result['auto_decision'],
            ]);

            return array_merge(['success' => true], $result);
        } catch (RuntimeException $e) {
            // Release reserved cards on error
            $this->inventoryService->releaseReservation($reservedCards);

            Log::error('Gift card purchase failed', [
                'user_id' => $user->id,
                'brand' => $brand,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Admin approve purchase.
     *
     * @param int $orderId
     * @param int|null $approvedBy
     * @return array
     */
    public function approvePurchase(int $orderId, ?int $approvedBy = null): array
    {
        $order = GiftcardOrder::findOrFail($orderId);

        if ($order->type !== 'buy') {
            throw new RuntimeException('Only buy orders can be approved');
        }

        if ($order->status !== 'pending_review') {
            throw new RuntimeException("Cannot approve order with status: {$order->status}");
        }

        try {
            return DB::transaction(function () use ($order, $approvedBy) {
                $cardIds = $order->metadata['card_ids'] ?? [];
                $reservationId = (string) data_get($order->metadata, 'reservation_id');
                if ($reservationId === '') {
                    throw new RuntimeException('Gift card reservation is missing.');
                }

                if (!data_get($order->metadata, 'settlement_reference')) {
                    $settlementReference = 'giftcard_purchase:'.$order->id;
                    $this->settlement->giftcardPurchaseSettle(
                        $reservationId,
                        $settlementReference,
                        (string) data_get($order->metadata, 'subtotal', $order->amount),
                        (string) data_get($order->metadata, 'platform_fee', '0'),
                        ['giftcard_order_id' => $order->id, 'approved_by' => $approvedBy],
                    );
                    $order->metadata = array_merge($order->metadata ?? [], [
                        'settlement_reference' => $settlementReference,
                        'settlement_path' => 'canonical_ledger',
                    ]);
                    $order->save();
                }

                // Fulfill inventory
                $cardModels = \App\Models\GiftCardInventory::query()
                    ->whereIn('id', $cardIds)
                    ->get();

                $this->inventoryService->fulfillCards($cardModels, $order->user_id, (string) $order->id);

                // Prepare delivery
                $deliverableCards = $this->deliveryService->prepareDelivery($order, $cardIds);

                // Update order status
                $order->update([
                    'status' => 'delivered',
                    'processed_at' => now(),
                    'delivered_at' => now(),
                    'metadata' => array_merge($order->metadata ?? [], ['delivery_state' => 'DELIVERED']),
                ]);

                Log::info('Gift card purchase approved by admin', [
                    'order_id' => $order->id,
                    'approved_by' => $approvedBy,
                ]);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'status' => 'delivered',
                    'cards' => $this->deliveryService->getInAppDelivery($order, $deliverableCards),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to approve purchase', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Admin reject purchase.
     *
     * @param int $orderId
     * @param int|null $rejectedBy
     * @param string $reason
     * @return array
     */
    public function rejectPurchase(int $orderId, ?int $rejectedBy = null, string $reason = 'Rejected by admin'): array
    {
        $order = GiftcardOrder::findOrFail($orderId);

        if ($order->type !== 'buy') {
            throw new RuntimeException('Only buy orders can be rejected');
        }

        if ($order->status !== 'pending_review') {
            throw new RuntimeException("Cannot reject order with status: {$order->status}");
        }

        try {
            return DB::transaction(function () use ($order, $reason) {
                $cardIds = $order->metadata['card_ids'] ?? [];

                // Release reserved cards
                $cardModels = \App\Models\GiftCardInventory::query()
                    ->whereIn('id', $cardIds)
                    ->get();

                $this->inventoryService->releaseReservation($cardModels);

                $reservationId = (string) data_get($order->metadata, 'reservation_id');
                if ($reservationId !== '') {
                    $this->reservations->release($reservationId, null, [
                        'rejection_reason' => $reason,
                        'giftcard_order_id' => $order->id,
                    ]);
                }

                // Update order
                $order->update([
                    'status' => 'failed',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'rejection_reason' => $reason,
                        'rejected_at' => now()->toIso8601String(),
                    ]),
                ]);

                Log::info('Gift card purchase rejected', [
                    'order_id' => $order->id,
                    'reason' => $reason,
                ]);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'status' => 'failed',
                    'refund_amount' => $order->amount,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to reject purchase', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

}
