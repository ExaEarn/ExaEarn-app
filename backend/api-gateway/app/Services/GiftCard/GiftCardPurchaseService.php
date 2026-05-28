<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Models\GiftcardOrder;
use App\Models\Wallet;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GiftCardPurchaseService
{
    private const SCALE = 8;

    public function __construct(
        private readonly GiftCardFeeCalculator $feeCalculator,
        private readonly LedgerService $ledgerService,
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
        float $cardValue,
        string $deliveryEmail,
        string $currency = 'USD',
        string $walletType = 'funding',
        array $metadata = []
    ): GiftcardOrder {
        return DB::transaction(function () use ($user, $brand, $cardValue, $deliveryEmail, $currency, $walletType, $metadata) {
            // 1. Calculate all fees
            $feeBreakdown = $this->feeCalculator->calculateFees($brand, $cardValue, $currency);

            // 2. Verify user has sufficient balance
            $wallet = $this->verifyAndLockWallet($user->id, $currency, (float) $feeBreakdown['total_cost_to_user']);

            // 3. Create order record with fee details
            $order = $this->createGiftcardOrder($user, $brand, $feeBreakdown, $walletType, $currency, $metadata);

            // 4. Deduct from wallet (atomic)
            $this->deductFromWallet($wallet, (float) $feeBreakdown['total_cost_to_user']);

            // 5. Record ledger entries (double-entry accounting)
            $this->recordLedgerEntries($user->id, $order->id, $feeBreakdown, $currency);

            // 6. Call external API to purchase (if configured)
            $this->callExternalGiftCardApi($order, $brand, $cardValue, $deliveryEmail);

            // 7. Update order status to completed
            $order->update([
                'status' => 'completed',
                'delivered_at' => now(),
                'metadata' => array_merge($order->metadata ?? [], [
                    'delivery_email' => $deliveryEmail,
                    'ledger_recorded' => true,
                ]),
            ]);

            return $order->fresh();
        });
    }

    /**
     * Verify user wallet and lock for update.
     */
    private function verifyAndLockWallet(int $userId, string $currency, float $totalCost): Wallet
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('currency', strtoupper($currency))
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            throw new RuntimeException("User wallet not found for {$currency}");
        }

        $availableBalance = (float) $wallet->available_balance;
        if ($availableBalance < $totalCost) {
            throw new RuntimeException(
                "Insufficient balance. Available: {$availableBalance}, Required: {$totalCost}"
            );
        }

        return $wallet;
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

    /**
     * Deduct amount from user's wallet.
     */
    private function deductFromWallet(Wallet $wallet, float $amount): void
    {
        $newBalance = bcsub(
            (string) $wallet->available_balance,
            (string) $amount,
            self::SCALE
        );

        if (bccomp($newBalance, '0', self::SCALE) < 0) {
            throw new RuntimeException('Wallet balance would go negative');
        }

        $wallet->update(['available_balance' => $newBalance]);
    }

    /**
     * Record all ledger entries for complete accounting.
     */
    private function recordLedgerEntries(int $userId, int $orderId, array $feeBreakdown, string $currency): void
    {
        $reference = "gcp:{$orderId}";

        $this->ledgerService->giftcardPurchase(
            $userId,
            (float) $feeBreakdown['card_value'],
            (float) $feeBreakdown['user_charge'],
            $currency,
            "{$reference}:purchase",
            $orderId
        );

        $apiFee = (float) $feeBreakdown['api_fee'];
        $deliveryFee = (float) $feeBreakdown['delivery_fee'];
        if ($apiFee > 0 || $deliveryFee > 0) {
            $this->ledgerService->giftcardApiFeeDeduction(
                $apiFee,
                $deliveryFee,
                $currency,
                "{$reference}:api_fee"
            );
        }

        $platformProfit = (float) $feeBreakdown['platform_profit'];
        if ($platformProfit > 0) {
            $this->ledgerService->recordPlatformProfit(
                $platformProfit,
                $currency,
                "{$reference}:profit",
                $orderId
            );
        }
    }

    /**
     * Call external gift card provider API.
     * Placeholder for actual API integration (Tango, Runa, Tillo, etc.)
     */
    private function callExternalGiftCardApi(
        GiftcardOrder $order,
        string $brand,
        float $cardValue,
        string $deliveryEmail
    ): void
    {
        // TODO: Implement actual API calls to:
        // - Tango Card API
        // - Runa API
        // - Tillo API
        // - etc.
        
        // For now, simulate successful purchase
        $order->update([
            'metadata' => array_merge($order->metadata ?? [], [
                'api_transaction_id' => 'api_' . uniqid(),
                'api_response' => [
                    'status' => 'success',
                    'brand' => $brand,
                    'amount' => $cardValue,
                    'delivery_email' => $deliveryEmail,
                ],
            ]),
        ]);
    }

    public function refundPurchase(int $orderId, string $reason = 'user_request'): GiftcardOrder
    {
        return DB::transaction(function () use ($orderId, $reason) {
            $order = GiftcardOrder::lockForUpdate()->findOrFail($orderId);

            if ($order->status === 'refunded') {
                throw new RuntimeException('Order already refunded');
            }

            $refundAmount = (float) $order->amount;

            // Restore wallet
            $wallet = Wallet::where('user_id', $order->user_id)
                ->where('currency', $order->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->update([
                'available_balance' => bcadd(
                    (string) $wallet->available_balance,
                    (string) $refundAmount,
                    self::SCALE
                ),
            ]);

            // Record refund in ledger (skip ledger entry to avoid negative balance issues in refund path)
            try {
                $this->ledgerService->giftcardRefund(
                    $order->user_id,
                    $refundAmount,
                    $order->currency,
                    "gcr:{$orderId}",
                    $orderId,
                    $reason
                );
            } catch (\Exception $e) {
                // If ledger refund fails (e.g., treasury account doesn't have balance), still complete the refund to user
                \Illuminate\Support\Facades\Log::warning("Ledger refund recording failed for order {$orderId}: " . $e->getMessage());
            }

            // Update order
            $order->update([
                'status' => 'refunded',
                'metadata' => array_merge($order->metadata ?? [], [
                    'refund_reason' => $reason,
                    'refunded_at' => now(),
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
