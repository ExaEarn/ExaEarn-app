<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\AdminApprovalJob;
use App\Jobs\FraudAnalysisJob;
use App\Jobs\ProcessGiftcardBuyJob;
use App\Jobs\ProcessGiftcardSellJob;
use App\Models\Giftcard;
use App\Models\GiftcardInventory;
use App\Models\GiftcardOrder;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\GiftCard\GiftCardPricingEngine;
use App\Services\GiftCard\GiftCardRateEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use RuntimeException;

class GiftcardService
{
    public function __construct(
        private readonly RiskEngineService $riskEngineService,
        private readonly LedgerService $ledgerService,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlement,
        private readonly GiftCardPricingEngine $pricingEngine,
        private readonly GiftCardRateEngine $rateEngine,
    ) {
    }

    public function inventory(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Giftcard::query()
            ->where('status', 'available')
            ->when(!empty($filters['card_type']), fn ($query) => $query->where('card_type', $filters['card_type']))
            ->when(!empty($filters['provider']), fn ($query) => $query->where('provider', $filters['provider']))
            ->when(!empty($filters['amount']), fn ($query) => $query->where('amount', $filters['amount']))
            ->paginate($perPage);
    }

    public function myOrders(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return GiftcardOrder::query()
            ->with('giftcard')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function reviewQueue(int $perPage = 20): LengthAwarePaginator
    {
        return GiftcardOrder::query()
            ->with(['user:id,name,email', 'giftcard'])
            ->where('requires_admin_review', true)
            ->whereIn('status', ['pending_review', 'flagged', 'pending'])
            ->latest()
            ->paginate($perPage);
    }

    public function submitSellOrder(User $user, array $payload, array $context = []): GiftcardOrder
    {
        $this->enforceRateLimit('sell', $user->id);

        $cardHash = $this->riskEngineService->hashCardCode((string) $payload['card_code']);
        if (Giftcard::query()->where('card_hash', $cardHash)->exists()) {
            throw new RuntimeException('Duplicate giftcard detected.');
        }

        return DB::transaction(function () use ($user, $payload, $context, $cardHash): GiftcardOrder {
            $order = GiftcardOrder::query()->create([
                'user_id' => $user->id,
                'type' => 'sell',
                'amount' => $payload['amount'],
                'currency' => $payload['currency'] ?? 'USD',
                'status' => 'pending_analysis',
                'payment_method' => $payload['payment_method'] ?? 'wallet_credit',
                'reference' => $this->reference('sell'),
                'metadata' => [
                    'source_mode' => $payload['source_mode'] ?? 'manual_upload',
                    'ip_address' => $context['ip_address'] ?? request()?->ip(),
                    'device_id' => $context['device_id'] ?? null,
                    'geo_location' => $context['geo_location'] ?? 'unknown',
                    'is_vpn' => $context['is_vpn'] ?? false,
                ],
            ]);

            $giftcard = Giftcard::query()->create([
                'owner_user_id' => $user->id,
                'order_id' => $order->id,
                'card_type' => $payload['card_type'],
                'provider' => $payload['provider'] ?? null,
                'amount' => $payload['amount'],
                'currency' => $payload['currency'] ?? 'USD',
                'encrypted_code' => Crypt::encryptString((string) $payload['card_code']),
                'card_hash' => $cardHash,
                'status' => 'submitted',
                'risk_level' => 'LOW',
                'verified_source' => (bool) data_get(config('giftcards.providers'), ($payload['provider'] ?? 'manual_upload') . '.verified_source', false),
                'metadata' => [
                    'source_mode' => $payload['source_mode'] ?? 'manual_upload',
                ],
            ]);

            $order->giftcard_id = $giftcard->id;
            $order->save();

            FraudAnalysisJob::dispatch($order->id)->onQueue('giftcards');

            return $order->fresh('giftcard');
        });
    }

    public function submitBuyOrder(User $user, array $payload, array $context = []): GiftcardOrder
    {
        $this->enforceRateLimit('buy', $user->id);

        $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 1;
        $selectedCards = $this->resolveInventoryGiftcards($payload, $quantity);
        if ($selectedCards->isEmpty() || $selectedCards->count() < $quantity) {
            throw new RuntimeException('Requested giftcard inventory is unavailable or insufficient quantity.');
        }

        $inventoryCurrency = strtoupper($payload['currency'] ?? $selectedCards->first()->currency);
        $paymentCurrency = strtoupper($payload['payment_currency'] ?? config('giftcards.currency', 'USDT'));
        $pricing = $this->pricingEngine->getBuyPrice(
            $selectedCards->first()->brand,
            (string) $selectedCards->first()->card_value,
            $inventoryCurrency,
            [
                'user_id' => $user->id,
                'provider' => strtolower((string) $selectedCards->first()->brand),
                'country' => $payload['country'] ?? null,
                'promotion_code' => $payload['promotion_code'] ?? null,
            ],
        );
        $unitPrice = (string) $pricing['buy_price'];
        $subtotal = FinancialDecimal::mul($unitPrice, (string) $quantity, 8);
        $platformDecision = $this->pricingEngine->calculateTotalPrice(
            $selectedCards->first()->brand,
            (string) $selectedCards->first()->card_value,
            $quantity,
            $inventoryCurrency,
            [
                'user_id' => $user->id,
                'provider' => strtolower((string) $selectedCards->first()->brand),
                'country' => $payload['country'] ?? null,
                'promotion_code' => $payload['promotion_code'] ?? null,
            ],
        );
        $platformFee = (string) $platformDecision['platform_fee'];
        $totalAmount = FinancialDecimal::add($subtotal, $platformFee, 8);

        $this->ledgerService->getOrCreateAccount($user->id, 'funding', $paymentCurrency);
        if (!$this->ledgerService->hasBalance($user->id, $totalAmount, $paymentCurrency, 'funding')) {
            throw new RuntimeException("Insufficient wallet balance. Required: {$totalAmount} {$paymentCurrency}");
        }

        $reservedCardIds = $selectedCards->pluck('id')->toArray();

        return DB::transaction(function () use ($user, $payload, $context, $selectedCards, $quantity, $paymentCurrency, $unitPrice, $subtotal, $platformFee, $totalAmount, $reservedCardIds) {
            $this->reserveInventoryCards($selectedCards);

            $reservation = $this->reservations->reserveUserAccount(
                $user->id,
                'funding',
                $paymentCurrency,
                $totalAmount,
                'giftcard_purchase',
                'giftcard_order',
                null,
                'giftcard-buy-order:'.$user->id.':'.$selectedCards->first()->brand.':'.Str::uuid(),
                ['card_ids' => $reservedCardIds],
            );

            $order = GiftcardOrder::query()->create([
                'user_id' => $user->id,
                'giftcard_id' => null,
                'type' => 'buy',
                'amount' => $totalAmount,
                'currency' => $paymentCurrency,
                'status' => 'pending_analysis',
                'payment_method' => $payload['payment_method'] ?? 'funding',
                'reference' => $this->reference('buy'),
                'metadata' => [
                    'delivery_mode' => 'secure_reveal',
                    'brand' => $selectedCards->first()->brand,
                    'card_value' => (string) $selectedCards->first()->card_value,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'platform_fee' => $platformFee,
                    'total_amount' => $totalAmount,
                    'reservation_id' => $reservation->reservation_id,
                    'pricing_snapshot' => [
                        'gross_amount' => $subtotal,
                        'provider_cost' => $subtotal,
                        'exaearn_fee' => $platformFee,
                        'currency' => $paymentCurrency,
                        'source' => 'PRICING_ENGINE',
                        'central_pricing' => $platformDecision['pricing_snapshot'] ?? $pricing['pricing_snapshot'] ?? null,
                        'captured_at' => now()->toIso8601String(),
                    ],
                    'card_ids' => $reservedCardIds,
                    'ip_address' => $context['ip_address'] ?? request()?->ip(),
                    'device_id' => $context['device_id'] ?? null,
                    'geo_location' => $context['geo_location'] ?? 'unknown',
                    'is_vpn' => $context['is_vpn'] ?? false,
                ],
            ]);

            $inventoryCard = $selectedCards->first();
            $giftcard = Giftcard::query()->create([
                'owner_user_id' => $user->id,
                'order_id' => $order->id,
                'card_type' => $inventoryCard->brand,
                'provider' => strtolower($inventoryCard->brand),
                'amount' => $inventoryCard->card_value,
                'currency' => $inventoryCard->currency,
                'encrypted_code' => $inventoryCard->encrypted_card_code,
                'card_hash' => hash('sha256', Crypt::decryptString((string) $inventoryCard->encrypted_card_code)),
                'status' => 'pending',
                'risk_level' => 'LOW',
                'verified_source' => (bool) data_get($inventoryCard->metadata, 'verified_source', false),
                'metadata' => array_merge($inventoryCard->metadata ?? [], [
                    'source_inventory_id' => $inventoryCard->id,
                ]),
            ]);

            $order->giftcard_id = $giftcard->id;
            $order->save();

            FraudAnalysisJob::dispatch($order->id)->onQueue('giftcards');

            return $order->fresh('giftcard');
        });
    }

    public function analyzeOrderRisk(int $orderId): GiftcardOrder
    {
        $order = GiftcardOrder::query()->with(['user', 'giftcard'])->findOrFail($orderId);
        $giftcard = $order->giftcard;

        $analysis = $this->riskEngineService->analyze($order->user, $order, [
            'ip_address' => data_get($order->metadata, 'ip_address'),
            'device_id' => data_get($order->metadata, 'device_id'),
            'geo_location' => data_get($order->metadata, 'geo_location'),
            'is_vpn' => data_get($order->metadata, 'is_vpn', false),
            'card_hash' => $giftcard?->card_hash,
            'exclude_giftcard_id' => $giftcard?->id,
            'verified_source' => (bool) ($giftcard?->verified_source ?? false),
        ]);

        $order->risk_score = $analysis['risk_score'];
        $order->risk_level = $analysis['risk_level'];
        $order->requires_admin_review = $this->riskEngineService->requiresAdminReview($analysis['risk_score']);
        $order->status = !$order->requires_admin_review
            ? 'approved'
            : ($analysis['risk_level'] === 'HIGH' ? 'flagged' : 'pending_review');
        $order->metadata = array_merge($order->metadata ?? [], [
            'fraud_reason' => $analysis['reason'],
            'fraud_payload' => $analysis['payload'],
        ]);
        $order->save();

        if ($giftcard) {
            $giftcard->risk_level = $analysis['risk_level'];
            $giftcard->save();
        }

        if ($order->type === 'sell') {
            if ($order->requires_admin_review) {
                AdminApprovalJob::dispatch($order->id, 'review')->onQueue('giftcards-admin');
            } else {
                ProcessGiftcardSellJob::dispatch($order->id)->onQueue('giftcards');
            }
        }

        if ($order->type === 'buy') {
            if ($order->requires_admin_review) {
                $order->status = $analysis['risk_level'] === 'HIGH' ? 'flagged' : 'pending_review';
                $order->metadata = array_merge($order->metadata ?? [], [
                    'financial_path' => 'canonical_reservation',
                ]);
                $order->save();
                AdminApprovalJob::dispatch($order->id, 'review')->onQueue('giftcards-admin');
            } else {
                ProcessGiftcardBuyJob::dispatch($order->id)->onQueue('giftcards');
            }
        }

        return $order->fresh('giftcard');
    }

    public function processSellOrder(int $orderId): GiftcardOrder
    {
        return DB::transaction(function () use ($orderId): GiftcardOrder {
            $order = GiftcardOrder::query()->with('giftcard')->lockForUpdate()->findOrFail($orderId);
            if ($order->processed_at) {
                return $order;
            }

            try {
                $reference = 'giftcard_sell_payout:'.$order->id;
                $this->settlement->giftcardSellPayout(
                    (int) $order->user_id,
                    (string) $order->currency,
                    (string) $order->amount,
                    $reference,
                    [
                        'giftcard_order_id' => $order->id,
                        'giftcard_id' => $order->giftcard_id,
                    ],
                );
            } catch (RuntimeException $exception) {
                throw $exception;
            }

            $order->status = 'completed';
            $order->processed_at = now();
            $order->metadata = array_merge($order->metadata ?? [], [
                'settlement_reference' => $reference,
                'settlement_path' => 'canonical_ledger',
            ]);
            $order->save();

            if ($order->giftcard) {
                $order->giftcard->status = 'available';
                $order->giftcard->save();
            }

            return $order->fresh('giftcard');
        });
    }

    public function processBuyOrder(int $orderId): GiftcardOrder
    {
        return DB::transaction(function () use ($orderId): GiftcardOrder {
            $order = GiftcardOrder::query()->with('giftcard')->lockForUpdate()->findOrFail($orderId);
            if ($order->delivered_at) {
                return $order;
            }

            $reservationId = (string) data_get($order->metadata, 'reservation_id');
            if ($reservationId === '') {
                throw new RuntimeException('Giftcard order is missing a canonical reservation.');
            }

            try {
                $cardIds = data_get($order->metadata, 'card_ids', []);
                $cards = GiftCardInventory::query()
                    ->whereIn('id', $cardIds)
                    ->lockForUpdate()
                    ->get();

                if ($cards->isEmpty() || $cards->count() !== count($cardIds)) {
                    throw new RuntimeException('Giftcard inventory is unavailable for delivery.');
                }

                $deliveryDetails = [];
                foreach ($cards as $card) {
                    $deliveryDetails[] = $this->maskGiftcardCode(Crypt::decryptString($card->encrypted_card_code));
                    $card->status = 'sold';
                    $card->sold_to_user_id = $order->user_id;
                    $card->sold_at = now();
                    $card->available = false;
                    $card->metadata = array_merge($card->metadata ?? [], [
                        'fulfilled_at' => now()->toIso8601String(),
                        'giftcard_order_id' => $order->id,
                    ]);
                    $card->save();
                }

                $settlementReference = 'giftcard_purchase:'.$order->id;
                $this->settlement->giftcardPurchaseSettle(
                    $reservationId,
                    $settlementReference,
                    (string) data_get($order->metadata, 'subtotal', $order->amount),
                    (string) data_get($order->metadata, 'platform_fee', '0'),
                    [
                        'giftcard_order_id' => $order->id,
                        'delivery_masked_codes' => $deliveryDetails,
                    ],
                );

                $order->status = 'completed';
                $order->processed_at = now();
                $order->delivered_at = now();
                $order->metadata = array_merge($order->metadata ?? [], [
                    'settlement_reference' => $settlementReference,
                    'settlement_path' => 'canonical_ledger',
                    'delivery' => [
                        'masked_codes' => $deliveryDetails,
                        'card_ids' => $cardIds,
                    ],
                ]);
                $order->save();

                return $order->fresh('giftcard');
            } catch (RuntimeException $exception) {
                $this->reservations->release($reservationId, 'Giftcard delivery failed before settlement: '.$exception->getMessage());

                throw $exception;
            }
        });
    }

    public function applyAdminDecision(int $orderId, string $decision, ?int $adminUserId = null, ?string $reason = null): GiftcardOrder
    {
        return DB::transaction(function () use ($orderId, $decision, $adminUserId, $reason): GiftcardOrder {
            $order = GiftcardOrder::query()->with('giftcard')->lockForUpdate()->findOrFail($orderId);

            if ($decision === 'review') {
                return $order;
            }

            if ($decision === 'approve') {
                if ($order->type === 'sell') {
                    return $this->processSellOrder($orderId);
                }

                if ($order->type === 'buy') {
                    $order->metadata = array_merge($order->metadata ?? [], [
                        'admin_user_id' => $adminUserId,
                        'admin_decision_reason' => $reason,
                    ]);
                    $order->save();

                    return $this->processBuyOrder($orderId);
                }

                return $order->fresh('giftcard');
            }

            if ($decision === 'reject') {
                if ($order->type === 'buy') {
                    $reservationId = (string) data_get($order->metadata, 'reservation_id');
                    if ($reservationId !== '') {
                        $this->reservations->release($reservationId, $reason ?? 'Admin rejected giftcard order.');
                    }
                }

                $order->status = 'rejected';
                $order->processed_at = now();
                $order->metadata = array_merge($order->metadata ?? [], [
                    'admin_user_id' => $adminUserId,
                    'admin_decision_reason' => $reason,
                ]);
                $order->save();

                if ($order->giftcard && $order->type === 'sell') {
                    $order->giftcard->status = 'rejected';
                    $order->giftcard->save();
                }

                return $order->fresh('giftcard');
            }

            throw new RuntimeException('Unsupported admin decision.');
        });
    }

    public function orderDetail(User $user, int $orderId): GiftcardOrder
    {
        $order = GiftcardOrder::query()->with(['giftcard', 'fraudLogs'])->findOrFail($orderId);
        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            throw new RuntimeException('You cannot access this order.');
        }

        return $order;
    }

    private function resolveInventoryGiftcards(array $payload, int $quantity): \Illuminate\Support\Collection
    {
        if (!empty($payload['giftcard_id'])) {
            $card = GiftCardInventory::query()
                ->whereKey((int) $payload['giftcard_id'])
                ->where('available', true)
                ->first();

            return $card ? collect([$card]) : collect();
        }

        $query = GiftCardInventory::query()
            ->where('available', true)
            ->where('brand', strtolower($payload['brand']))
            ->where('card_value', (float) $payload['card_value'])
            ->when(!empty($payload['currency']), fn ($query) => $query->where('currency', strtoupper($payload['currency'])));

        return $query->lockForUpdate()
            ->limit($quantity)
            ->get();
    }

    private function reserveInventoryCards(\Illuminate\Support\Collection $cards): void
    {
        foreach ($cards as $card) {
            $card->update([
                'available' => false,
                'metadata' => array_merge($card->metadata ?? [], [
                    'reserved_at' => now()->toIso8601String(),
                    'reservation_expires_at' => now()->addMinutes(15)->toIso8601String(),
                ]),
            ]);
        }
    }

    private function enforceRateLimit(string $type, int $userId): void
    {
        $key = "giftcards:{$type}:{$userId}";
        $maxAttempts = (int) config("giftcards.limits.{$type}_per_minute", 3);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new RuntimeException('Giftcard rate limit exceeded. Please retry shortly.');
        }

        RateLimiter::hit($key, 60);
    }

    private function reference(string $prefix): string
    {
        return strtoupper($prefix . '-' . Str::random(18));
    }

    private function maskGiftcardCode(string $code): string
    {
        $visible = max(0, strlen($code) - 4);
        return str_repeat('*', $visible) . substr($code, -4);
    }

}
