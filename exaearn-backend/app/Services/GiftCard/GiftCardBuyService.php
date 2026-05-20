<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftcardOrder;
use App\Models\Wallet;
use App\Models\User;
use App\Repositories\WalletRepository;
use App\Services\LedgerService;
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
        private readonly WalletRepository $walletRepository,
        private readonly LedgerService $ledgerService,
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
        float $cardValue,
        int $quantity,
        string $currency = 'USD',
        string $paymentWalletCurrency = 'USD'
    ): array {
        // 1. Validate request
        if ($quantity < 1 || $quantity > 100) {
            throw new RuntimeException('Quantity must be between 1 and 100');
        }

        // 2. Check inventory availability
        $availability = $this->inventoryService->checkAvailability($brand, $cardValue, $quantity);
        if (!$availability['available']) {
            throw new RuntimeException(
                "Insufficient inventory. Required: {$quantity}, Available: {$availability['count']}"
            );
        }

        // 3. Calculate pricing
        $pricing = $this->pricingEngine->calculateTotalPrice($brand, $cardValue, $quantity, $currency);
        $totalPayment = $pricing['total'];

        // 4. Check wallet balance
        $wallet = $this->walletRepository->findOrCreate($user->id, $paymentWalletCurrency);
        if ($wallet->available_balance < $totalPayment) {
            throw new RuntimeException(
                "Insufficient wallet balance. Required: {$totalPayment}, Available: {$wallet->available_balance}"
            );
        }

        // 5. Perform fraud analysis
        $fraudAnalysis = $this->fraudDetection->analyzeRisk($user, $brand, $totalPayment, $quantity);

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
                $wallet,
                $fraudAnalysis,
                $reservedCards
            ) {
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
                    ],
                ]);

                // 8b. Debit wallet
                $wallet->available_balance -= $totalPayment;
                $wallet->locked_balance += $totalPayment;
                $wallet->save();

                // 8c. Create ledger entries (double-entry)
                $reference = $order->reference;

                // Debit user wallet
                $this->ledgerService->addEntry(
                    account_id: $wallet->id,
                    amount: (string) (-$totalPayment),
                    asset: $paymentWalletCurrency,
                    reference: $reference,
                    type: 'giftcard_purchase_debit',
                    userId: $user->id,
                    metadata: [
                        'order_id' => $order->id,
                        'brand' => $brand,
                        'quantity' => $quantity,
                    ]
                );

                // Credit platform treasury
                $treasuryAccount = $this->getTreasuryAccount($paymentWalletCurrency);
                $this->ledgerService->addEntry(
                    account_id: $treasuryAccount->id,
                    amount: (string) $totalPayment,
                    asset: $paymentWalletCurrency,
                    reference: $reference,
                    type: 'giftcard_purchase_credit',
                    metadata: [
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'brand' => $brand,
                    ]
                );

                // 8d. Fulfill inventory for auto-approved orders
                if ($fraudAnalysis['auto_decision'] === 'approve') {
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

                // Refund wallet
                $wallet = $this->walletRepository->findOrCreate(
                    $order->user_id,
                    $order->currency
                );

                $wallet->available_balance += $order->amount;
                $wallet->locked_balance -= $order->amount;
                $wallet->save();

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

    /**
     * Get treasury account (create if not exists).
     *
     * @param string $currency
     * @return Wallet
     */
    private function getTreasuryAccount(string $currency): Wallet
    {
        // TODO: Create a dedicated treasury account/wallet for gift card platform
        // For now, use a system account
        $treasuryUser = User::query()
            ->where('email', 'treasury@exaearn.local')
            ->firstOrCreate(['email' => 'treasury@exaearn.local'], [
                'name' => 'Gift Card Treasury',
                'password' => bcrypt(str_random(32)),
            ]);

        return $this->walletRepository->findOrCreate($treasuryUser->id, $currency);
    }
}
