<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Jobs\ExpireP2PTradeJob;
use App\Jobs\ModerateP2PMessageJob;
use App\Models\AuditLog;
use App\Models\P2PAd;
use App\Models\P2PDispute;
use App\Models\P2PMessage;
use App\Models\P2PRating;
use App\Models\P2PTrade;
use App\Models\SuspiciousUser;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class P2PService
{
    private const SCALE = 8;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly TransactionService $transactions,
        private readonly BlockchainService $blockchain,
    ) {
    }

    public function listAds(array $filters = []): LengthAwarePaginator
    {
        $query = P2PAd::query()
            ->with('user')
            ->where('status', 'active');

        if (!empty($filters['type'])) {
            $query->where('type', strtolower((string) $filters['type']));
        }

        if (!empty($filters['asset'])) {
            $query->where('asset', strtoupper((string) $filters['asset']));
        }

        if (!empty($filters['fiat_currency'])) {
            $query->where('fiat_currency', strtoupper((string) $filters['fiat_currency']));
        }

        if (!empty($filters['region'])) {
            $query->where('region', strtoupper((string) $filters['region']));
        }

        if (!empty($filters['payment_method'])) {
            $query->whereJsonContains('payment_methods', (string) $filters['payment_method']);
        }

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }

        return $query->latest()->paginate((int) ($filters['per_page'] ?? 20))
            ->through(fn (P2PAd $ad) => $this->transformAd($ad));
    }

    public function myAds(User $user): Collection
    {
        return P2PAd::query()
            ->where('user_id', $user->id)
            ->with('user')
            ->latest()
            ->get()
            ->map(fn (P2PAd $ad) => $this->transformAd($ad));
    }

    public function createAd(User $user, array $payload): P2PAd
    {
        $this->guardUserRiskProfile($user, 'create_ad');

        if (($payload['requires_kyc'] ?? false) && !$user->kyc_verified_at) {
            throw new RuntimeException('KYC verification is required for this advertisement.');
        }

        $ad = P2PAd::query()->create([
            'ad_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => strtolower((string) $payload['type']),
            'asset' => strtoupper((string) $payload['asset']),
            'fiat_currency' => strtoupper((string) $payload['fiat_currency']),
            'price' => (string) $payload['price'],
            'min_limit' => (string) $payload['min_limit'],
            'max_limit' => (string) $payload['max_limit'],
            'available_amount' => (string) $payload['available_amount'],
            'payment_methods' => array_values($payload['payment_methods']),
            'region' => isset($payload['region']) ? strtoupper((string) $payload['region']) : null,
            'payment_time_limit_minutes' => (int) $payload['payment_time_limit_minutes'],
            'terms_of_trade' => $payload['terms_of_trade'] ?? null,
            'requires_kyc' => (bool) ($payload['requires_kyc'] ?? false),
            'status' => 'active',
            'metadata' => [
                'creator_email_verified' => (bool) $user->email_verified_at,
                'creator_kyc_verified' => (bool) $user->kyc_verified_at,
            ],
        ]);

        $this->logAudit($user->id, 'p2p_ad_created', [
            'ad_id' => $ad->id,
            'ad_uuid' => $ad->ad_uuid,
            'asset' => $ad->asset,
            'type' => $ad->type,
        ]);

        return $ad->load('user');
    }

    public function openTrade(User $user, int $adId, array $payload): P2PTrade
    {
        return DB::transaction(function () use ($user, $adId, $payload): P2PTrade {
            /** @var P2PAd $ad */
            $ad = P2PAd::query()->with('user')->lockForUpdate()->findOrFail($adId);

            if ($ad->status !== 'active') {
                throw new RuntimeException('Advertisement is not active.');
            }

            if ($ad->user_id === $user->id) {
                throw new RuntimeException('You cannot open a trade against your own advertisement.');
            }

            if ($ad->requires_kyc && !$user->kyc_verified_at) {
                throw new RuntimeException('This advertisement requires KYC verification.');
            }

            $paymentMethod = (string) $payload['payment_method'];
            if (!in_array($paymentMethod, $ad->payment_methods ?? [], true)) {
                throw new RuntimeException('Selected payment method is not supported by this advertisement.');
            }

            $fiatAmount = (string) $payload['fiat_amount'];
            $this->guardUserRiskProfile($user, 'open_trade', $fiatAmount);

            if ($this->compare($fiatAmount, (string) $ad->min_limit) < 0 || $this->compare($fiatAmount, (string) $ad->max_limit) > 0) {
                throw new RuntimeException('Trade amount is outside advertisement limits.');
            }

            $cryptoAmount = $this->div($fiatAmount, (string) $ad->price);
            if ($this->compare($cryptoAmount, (string) $ad->available_amount) > 0) {
                throw new RuntimeException('Advertisement does not have enough available liquidity.');
            }

            $buyerId = $ad->type === 'sell' ? $user->id : $ad->user_id;
            $sellerId = $ad->type === 'sell' ? $ad->user_id : $user->id;

            $escrowTransaction = $this->transactions->createTransaction(
                $sellerId,
                TransactionType::P2PEscrowLock,
                $ad->asset,
                $cryptoAmount,
                'p2p_escrow:' . (string) Str::uuid(),
                [
                    'ad_id' => $ad->id,
                    'buyer_id' => $buyerId,
                    'seller_id' => $sellerId,
                    'source' => 'p2p_trade',
                ]
            );

            $this->wallets->freezeFromTransaction($escrowTransaction);

            $ad->available_amount = $this->sub((string) $ad->available_amount, $cryptoAmount);
            if ($this->compare((string) $ad->available_amount, '0') === 0) {
                $ad->status = 'filled';
            }
            $ad->save();

            $trade = P2PTrade::query()->create([
                'trade_uuid' => (string) Str::uuid(),
                'ad_id' => $ad->id,
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'escrow_holder_user_id' => $sellerId,
                'asset' => $ad->asset,
                'fiat_currency' => $ad->fiat_currency,
                'crypto_amount' => $cryptoAmount,
                'fiat_amount' => $fiatAmount,
                'price' => (string) $ad->price,
                'payment_method' => $paymentMethod,
                'payment_window_minutes' => $ad->payment_time_limit_minutes,
                'payment_deadline' => now()->addMinutes($ad->payment_time_limit_minutes),
                'status' => 'pending',
                'escrow_transaction_id' => $escrowTransaction->id,
                'metadata' => [
                    'opened_by' => $user->id,
                    'ad_type' => $ad->type,
                    'terms_of_trade' => $ad->terms_of_trade,
                ],
            ]);

            $this->createSystemMessage($trade, sprintf(
                'Trade opened. %s %s has been locked in escrow. Buyer must pay within %d minutes.',
                $trade->crypto_amount,
                $trade->asset,
                $trade->payment_window_minutes
            ));

            ExpireP2PTradeJob::dispatch($trade->id)->delay($trade->payment_deadline);
            $this->publishTradeEvent($trade->fresh(['ad', 'buyer', 'seller']), 'trade_opened');

            return $trade->fresh(['ad', 'buyer', 'seller']);
        });
    }

    public function myTrades(User $user, ?string $status = null): Collection
    {
        $query = P2PTrade::query()
            ->with(['ad.user', 'buyer', 'seller'])
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get()->map(fn (P2PTrade $trade) => $this->transformTrade($trade));
    }

    public function showTradeForUser(User $user, string $tradeUuid): array
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        return $this->transformTrade($trade->load(['ad.user', 'buyer', 'seller']));
    }

    public function markPaymentSent(User $user, string $tradeUuid, array $payload = []): P2PTrade
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        if ($trade->buyer_id !== $user->id) {
            throw new RuntimeException('Only the buyer can mark payment as sent.');
        }

        if ($trade->status !== 'pending') {
            throw new RuntimeException('Payment can only be marked on pending trades.');
        }

        $trade->status = 'payment_sent';
        $trade->payment_sent_at = now();
        $trade->metadata = array_merge($trade->metadata ?? [], [
            'payment_reference' => $payload['payment_reference'] ?? null,
            'payment_proof_attachment' => $payload['attachment'] ?? null,
        ]);
        $trade->save();

        $this->createSystemMessage($trade, 'Buyer marked payment as sent. Seller should verify and release escrow.');
        $this->publishTradeEvent($trade->fresh(['ad', 'buyer', 'seller']), 'payment_sent');

        return $trade->fresh(['ad', 'buyer', 'seller']);
    }

    public function releaseTrade(User $user, string $tradeUuid): P2PTrade
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        if ($trade->seller_id !== $user->id && $user->role !== 'admin') {
            throw new RuntimeException('Only the seller or admin can release escrow.');
        }

        return $this->releaseEscrowToBuyer($trade, $user->id);
    }

    public function cancelTrade(User $user, string $tradeUuid): P2PTrade
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        if ($trade->status !== 'pending') {
            throw new RuntimeException('Only pending trades can be cancelled.');
        }

        return $this->returnEscrowToSeller($trade, $user->id, 'Trade cancelled before payment confirmation.');
    }

    public function openDispute(User $user, string $tradeUuid, array $payload): P2PDispute
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        if (in_array($trade->status, ['released', 'cancelled'], true)) {
            throw new RuntimeException('Completed trades cannot be disputed.');
        }

        $existing = P2PDispute::query()
            ->where('trade_id', $trade->id)
            ->whereIn('status', ['open', 'under_review', 'info_requested'])
            ->first();
        if ($existing) {
            throw new RuntimeException('An active dispute already exists for this trade.');
        }

        $trade->status = 'disputed';
        $trade->disputed_at = now();
        $trade->save();

        $dispute = P2PDispute::query()->create([
            'trade_id' => $trade->id,
            'opened_by' => $user->id,
            'reason' => (string) $payload['reason'],
            'evidence' => $payload['evidence'] ?? [],
            'status' => 'open',
        ]);

        $counterpartyId = $trade->seller_id === $user->id ? $trade->buyer_id : $trade->seller_id;
        $this->recordSuspiciousActivity($counterpartyId, 'medium', [
            'source' => 'p2p_dispute',
            'trade_uuid' => $trade->trade_uuid,
            'opened_by' => $user->id,
        ]);

        $this->createSystemMessage($trade, 'Dispute opened. Escrow remains locked until admin resolution.');
        $this->publishTradeEvent($trade->fresh(['ad', 'buyer', 'seller']), 'trade_disputed', [
            'dispute_id' => $dispute->id,
        ]);

        return $dispute->fresh(['trade', 'openedBy']);
    }

    public function reviewQueue(): Collection
    {
        return P2PDispute::query()
            ->with(['trade.ad.user', 'trade.buyer', 'trade.seller', 'openedBy'])
            ->whereIn('status', ['open', 'under_review', 'info_requested'])
            ->latest()
            ->get();
    }

    public function resolveDispute(User $admin, int $disputeId, array $payload): P2PDispute
    {
        /** @var P2PDispute $dispute */
        $dispute = P2PDispute::query()->with('trade')->findOrFail($disputeId);
        $trade = $dispute->trade;
        $action = (string) $payload['action'];
        $notes = (string) ($payload['resolution'] ?? '');

        if (!in_array($dispute->status, ['open', 'under_review', 'info_requested'], true)) {
            throw new RuntimeException('This dispute has already been resolved.');
        }

        if ($action === 'request_more_info') {
            $dispute->status = 'info_requested';
            $dispute->resolution = $notes !== '' ? $notes : 'Additional proof requested.';
            $dispute->resolved_by = $admin->id;
            $dispute->save();
            $this->createSystemMessage($trade, 'Admin requested additional proof for the dispute.');
            return $dispute->fresh(['trade', 'openedBy', 'resolvedBy']);
        }

        if ($action === 'release_buyer') {
            $trade = $this->releaseEscrowToBuyer($trade, $admin->id);
            $dispute->resolution = $notes !== '' ? $notes : 'Escrow released to buyer.';
        } elseif ($action === 'return_seller') {
            $trade = $this->returnEscrowToSeller($trade, $admin->id, $notes !== '' ? $notes : 'Escrow returned to seller.');
            $dispute->resolution = $notes !== '' ? $notes : 'Escrow returned to seller.';
        } else {
            throw new RuntimeException('Unsupported dispute action.');
        }

        $dispute->status = 'resolved';
        $dispute->resolved_by = $admin->id;
        $dispute->resolved_at = now();
        $dispute->save();

        $this->publishTradeEvent($trade->fresh(['ad', 'buyer', 'seller']), 'dispute_resolved', [
            'dispute_id' => $dispute->id,
            'action' => $action,
        ]);

        return $dispute->fresh(['trade', 'openedBy', 'resolvedBy']);
    }

    public function messages(User $user, string $tradeUuid): Collection
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);

        return $trade->messages()
            ->with('sender')
            ->latest('id')
            ->get()
            ->reverse()
            ->values()
            ->map(fn (P2PMessage $message) => $this->transformMessage($message));
    }

    public function sendMessage(User $user, string $tradeUuid, array $payload): P2PMessage
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        if (in_array($trade->status, ['released', 'cancelled'], true)) {
            throw new RuntimeException('Trade chat is closed for completed trades.');
        }

        $message = P2PMessage::query()->create([
            'trade_id' => $trade->id,
            'sender_id' => $user->id,
            'encrypted_message' => isset($payload['message']) ? encrypt((string) $payload['message']) : null,
            'attachment' => $payload['attachment'] ?? null,
            'moderation_status' => 'pending',
            'moderation_flags' => [],
        ]);

        ModerateP2PMessageJob::dispatch($message->id);

        return $message->fresh('sender');
    }

    public function moderateMessage(int $messageId): void
    {
        /** @var P2PMessage $message */
        $message = P2PMessage::query()->with(['trade', 'sender'])->findOrFail($messageId);
        $body = $message->message;

        try {
            $result = $this->blockchain->moderateP2PMessage([
                'trade_uuid' => $message->trade->trade_uuid,
                'message' => $body,
                'attachment' => $message->attachment,
                'sender_id' => $message->sender_id,
            ]);
        } catch (\Throwable) {
            $result = $this->fallbackModeration($body);
        }

        $message->moderation_status = $result['status'] ?? 'clear';
        $message->moderation_flags = $result['flags'] ?? [];
        $message->save();

        if ($message->moderation_status === 'flagged') {
            $this->recordSuspiciousActivity($message->sender_id, 'high', [
                'source' => 'p2p_chat',
                'trade_uuid' => $message->trade->trade_uuid,
                'flags' => $message->moderation_flags,
            ]);
        }

        $this->publishChatEvent($message->trade, 'chat_message', array_merge(
            $this->transformMessage($message->fresh('sender')),
            ['warning' => $result['warning'] ?? null]
        ));
    }

    public function expireTrade(int $tradeId): void
    {
        /** @var P2PTrade|null $trade */
        $trade = P2PTrade::query()->find($tradeId);
        if (!$trade || $trade->status !== 'pending' || $trade->payment_deadline?->isFuture()) {
            return;
        }

        $this->returnEscrowToSeller($trade, $trade->seller_id, 'Trade expired before payment confirmation.');
    }

    public function rateTrade(User $user, string $tradeUuid, array $payload): P2PRating
    {
        $trade = $this->getTradeForUser($user, $tradeUuid);
        if ($trade->status !== 'released') {
            throw new RuntimeException('Only completed trades can be rated.');
        }

        $ratedUserId = $trade->buyer_id === $user->id ? $trade->seller_id : $trade->buyer_id;

        return P2PRating::query()->updateOrCreate(
            [
                'trade_id' => $trade->id,
                'rater_user_id' => $user->id,
            ],
            [
                'rated_user_id' => $ratedUserId,
                'score' => (int) $payload['score'],
                'comment' => $payload['comment'] ?? null,
            ]
        );
    }

    private function releaseEscrowToBuyer(P2PTrade $trade, int $actorUserId): P2PTrade
    {
        return DB::transaction(function () use ($trade, $actorUserId): P2PTrade {
            /** @var P2PTrade $trade */
            $trade = P2PTrade::query()->with(['ad', 'buyer', 'seller', 'escrowTransaction'])->lockForUpdate()->findOrFail($trade->id);
            if (!in_array($trade->status, ['payment_sent', 'disputed'], true)) {
                throw new RuntimeException('Trade is not ready for escrow release.');
            }

            /** @var Transaction $escrowTransaction */
            $escrowTransaction = $trade->escrowTransaction()->firstOrFail();
            $this->wallets->settleFrozenFromTransaction($escrowTransaction);
            $this->transactions->confirmTransaction($escrowTransaction, [
                'escrow_settled_at' => now()->toISOString(),
                'trade_uuid' => $trade->trade_uuid,
            ]);

            $releaseTransaction = $this->transactions->createTransaction(
                $trade->buyer_id,
                TransactionType::P2PEscrowRelease,
                $trade->asset,
                (string) $trade->crypto_amount,
                'p2p_release:' . $trade->trade_uuid,
                [
                    'trade_uuid' => $trade->trade_uuid,
                    'seller_id' => $trade->seller_id,
                    'buyer_id' => $trade->buyer_id,
                ]
            );
            $this->wallets->creditFromTransaction($releaseTransaction);
            $this->transactions->confirmTransaction($releaseTransaction, [
                'released_at' => now()->toISOString(),
            ]);

            $trade->status = 'released';
            $trade->release_transaction_id = $releaseTransaction->id;
            $trade->released_at = now();
            $trade->completed_at = now();
            $trade->save();

            $this->createSystemMessage($trade, 'Escrow released to buyer. Trade completed.');
            $this->publishTradeEvent($trade->fresh(['ad', 'buyer', 'seller']), 'trade_released');
            $this->logAudit($actorUserId, 'p2p_trade_released', ['trade_uuid' => $trade->trade_uuid]);

            return $trade->fresh(['ad', 'buyer', 'seller']);
        });
    }

    private function returnEscrowToSeller(P2PTrade $trade, int $actorUserId, string $reason): P2PTrade
    {
        return DB::transaction(function () use ($trade, $actorUserId, $reason): P2PTrade {
            /** @var P2PTrade $trade */
            $trade = P2PTrade::query()->with(['ad', 'buyer', 'seller', 'escrowTransaction'])->lockForUpdate()->findOrFail($trade->id);
            if (in_array($trade->status, ['released', 'cancelled'], true)) {
                return $trade;
            }

            /** @var Transaction $escrowTransaction */
            $escrowTransaction = $trade->escrowTransaction()->firstOrFail();
            $this->wallets->unfreezeFromTransaction($escrowTransaction);
            $this->transactions->confirmTransaction($escrowTransaction, [
                'escrow_returned_at' => now()->toISOString(),
                'return_reason' => $reason,
            ]);

            $returnTransaction = $this->transactions->createTransaction(
                $trade->seller_id,
                TransactionType::P2PEscrowReturn,
                $trade->asset,
                (string) $trade->crypto_amount,
                'p2p_return:' . $trade->trade_uuid,
                [
                    'trade_uuid' => $trade->trade_uuid,
                    'reason' => $reason,
                ]
            );
            $this->transactions->confirmTransaction($returnTransaction, [
                'wallet_effect' => 'release_via_escrow',
            ]);

            $trade->ad->available_amount = $this->add((string) $trade->ad->available_amount, (string) $trade->crypto_amount);
            if ($trade->ad->status === 'filled') {
                $trade->ad->status = 'active';
            }
            $trade->ad->save();

            $trade->status = 'cancelled';
            $trade->return_transaction_id = $returnTransaction->id;
            $trade->cancelled_at = now();
            $trade->completed_at = now();
            $trade->save();

            $this->createSystemMessage($trade, $reason);
            $this->publishTradeEvent($trade->fresh(['ad', 'buyer', 'seller']), 'trade_cancelled');
            $this->logAudit($actorUserId, 'p2p_trade_cancelled', [
                'trade_uuid' => $trade->trade_uuid,
                'reason' => $reason,
            ]);

            return $trade->fresh(['ad', 'buyer', 'seller']);
        });
    }

    private function getTradeForUser(User $user, string $tradeUuid): P2PTrade
    {
        /** @var P2PTrade|null $trade */
        $trade = P2PTrade::query()
            ->with(['ad.user', 'buyer', 'seller', 'escrowTransaction'])
            ->where('trade_uuid', $tradeUuid)
            ->first();

        if (!$trade || (!in_array($user->id, [$trade->buyer_id, $trade->seller_id], true) && $user->role !== 'admin')) {
            throw new RuntimeException('Trade not found.');
        }

        return $trade;
    }

    private function transformAd(P2PAd $ad): array
    {
        $completedTrades = P2PTrade::query()
            ->where(function (Builder $builder) use ($ad): void {
                $builder->where('buyer_id', $ad->user_id)->orWhere('seller_id', $ad->user_id);
            })
            ->where('status', 'released')
            ->count();

        $tradeCount = P2PTrade::query()
            ->where(function (Builder $builder) use ($ad): void {
                $builder->where('buyer_id', $ad->user_id)->orWhere('seller_id', $ad->user_id);
            })
            ->count();

        $rating = P2PRating::query()->where('rated_user_id', $ad->user_id)->avg('score');

        return [
            'id' => $ad->id,
            'ad_uuid' => $ad->ad_uuid,
            'type' => $ad->type,
            'asset' => $ad->asset,
            'fiat_currency' => $ad->fiat_currency,
            'price' => (string) $ad->price,
            'min_limit' => (string) $ad->min_limit,
            'max_limit' => (string) $ad->max_limit,
            'available_amount' => (string) $ad->available_amount,
            'payment_methods' => $ad->payment_methods ?? [],
            'region' => $ad->region,
            'payment_time_limit_minutes' => $ad->payment_time_limit_minutes,
            'terms_of_trade' => $ad->terms_of_trade,
            'status' => $ad->status,
            'merchant' => [
                'id' => $ad->user?->id,
                'name' => $ad->user?->name,
                'email_verified' => (bool) $ad->user?->email_verified_at,
                'kyc_verified' => (bool) $ad->user?->kyc_verified_at,
                'completed_trades' => $completedTrades,
                'completion_rate' => $tradeCount > 0 ? round(($completedTrades / $tradeCount) * 100, 2) : 0,
                'avg_rating' => $rating ? round((float) $rating, 2) : null,
                'verified_merchant' => (bool) $ad->user?->kyc_verified_at || $completedTrades >= config('p2p.merchant_badge_min_completed_trades', 25),
            ],
        ];
    }

    private function transformTrade(P2PTrade $trade): array
    {
        return [
            'id' => $trade->id,
            'trade_uuid' => $trade->trade_uuid,
            'status' => $trade->status,
            'asset' => $trade->asset,
            'fiat_currency' => $trade->fiat_currency,
            'crypto_amount' => (string) $trade->crypto_amount,
            'fiat_amount' => (string) $trade->fiat_amount,
            'price' => (string) $trade->price,
            'payment_method' => $trade->payment_method,
            'payment_window_minutes' => $trade->payment_window_minutes,
            'payment_deadline' => $trade->payment_deadline?->toISOString(),
            'payment_sent_at' => $trade->payment_sent_at?->toISOString(),
            'released_at' => $trade->released_at?->toISOString(),
            'completed_at' => $trade->completed_at?->toISOString(),
            'buyer' => [
                'id' => $trade->buyer?->id,
                'name' => $trade->buyer?->name,
            ],
            'seller' => [
                'id' => $trade->seller?->id,
                'name' => $trade->seller?->name,
            ],
            'ad' => $trade->ad ? $this->transformAd($trade->ad->loadMissing('user')) : null,
            'metadata' => $trade->metadata ?? [],
            'active_dispute' => $trade->disputes()->whereIn('status', ['open', 'under_review', 'info_requested'])->latest()->first(),
        ];
    }

    private function transformMessage(P2PMessage $message): array
    {
        return [
            'id' => $message->id,
            'trade_id' => $message->trade_id,
            'sender' => [
                'id' => $message->sender?->id,
                'name' => $message->sender?->name,
            ],
            'message' => $message->message,
            'attachment' => $message->attachment,
            'moderation_status' => $message->moderation_status,
            'moderation_flags' => $message->moderation_flags ?? [],
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function createSystemMessage(P2PTrade $trade, string $message): void
    {
        $systemMessage = P2PMessage::query()->create([
            'trade_id' => $trade->id,
            'sender_id' => $trade->seller_id,
            'encrypted_message' => encrypt($message),
            'attachment' => null,
            'moderation_status' => 'clear',
            'moderation_flags' => ['system'],
        ]);

        $this->publishChatEvent($trade, 'chat_system_message', $this->transformMessage($systemMessage->fresh('sender')));
    }

    private function publishTradeEvent(P2PTrade $trade, string $event, array $payload = []): void
    {
        try {
            $this->blockchain->publishP2PTradeEvent([
                'trade_uuid' => $trade->trade_uuid,
                'event' => $event,
                'payload' => array_merge($this->transformTrade($trade), $payload),
            ]);
        } catch (\Throwable) {
        }
    }

    private function publishChatEvent(P2PTrade $trade, string $event, array $payload): void
    {
        try {
            $this->blockchain->publishP2PTradeEvent([
                'trade_uuid' => $trade->trade_uuid,
                'event' => $event,
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
        }
    }

    private function guardUserRiskProfile(User $user, string $source, ?string $fiatAmount = null): void
    {
        if (
            $fiatAmount !== null
            && !$user->email_verified_at
            && $this->compare($fiatAmount, (string) config('p2p.new_user_trade_limit', 100)) > 0
        ) {
            throw new RuntimeException('New users have lower trade limits until verification is completed.');
        }

        $recentCancelled = P2PTrade::query()
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->where('status', 'cancelled')
            ->where('updated_at', '>=', now()->subDays(14))
            ->count();

        $recentDisputes = P2PDispute::query()
            ->whereHas('trade', function (Builder $builder) use ($user): void {
                $builder->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentCancelled >= config('p2p.max_recent_cancellations', 5) || $recentDisputes >= config('p2p.max_recent_disputes', 3)) {
            $this->recordSuspiciousActivity($user->id, 'high', [
                'source' => $source,
                'recent_cancelled_trades' => $recentCancelled,
                'recent_disputes' => $recentDisputes,
            ]);
        }
    }

    private function recordSuspiciousActivity(int $userId, string $riskLevel, array $metadata): void
    {
        $record = SuspiciousUser::query()->firstOrNew(['user_id' => $userId]);
        $record->risk_level = strtoupper($riskLevel);
        $record->flag_count = (int) ($record->flag_count ?? 0) + 1;
        $record->status = $record->flag_count >= 3 ? 'monitor' : 'open';
        $record->metadata = array_merge($record->metadata ?? [], [
            'last_p2p_event' => $metadata,
            'updated_at' => now()->toISOString(),
        ]);
        $record->save();
    }

    private function fallbackModeration(?string $body): array
    {
        $flags = [];
        foreach (config('p2p.chat_flag_keywords', []) as $keyword) {
            if ($body !== null && str_contains(strtolower($body), strtolower((string) $keyword))) {
                $flags[] = $keyword;
            }
        }

        return [
            'status' => $flags === [] ? 'clear' : 'flagged',
            'flags' => $flags,
            'warning' => $flags === [] ? null : 'Possible off-platform payment or scam attempt detected.',
        ];
    }

    private function logAudit(int $userId, string $action, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'device' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, self::SCALE);
        }

        return number_format(((float) $left + (float) $right), self::SCALE, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        if (function_exists('bcsub')) {
            return bcsub($left, $right, self::SCALE);
        }

        return number_format(((float) $left - (float) $right), self::SCALE, '.', '');
    }

    private function div(string $left, string $right): string
    {
        if ($this->compare($right, '0') <= 0) {
            throw new RuntimeException('Invalid trade price.');
        }

        if (function_exists('bcdiv')) {
            return bcdiv($left, $right, self::SCALE);
        }

        return number_format(((float) $left / (float) $right), self::SCALE, '.', '');
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, self::SCALE);
        }

        $leftFloat = (float) $left;
        $rightFloat = (float) $right;

        return $leftFloat < $rightFloat ? -1 : ($leftFloat > $rightFloat ? 1 : 0);
    }
}
