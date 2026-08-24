<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\CardCustomer;
use App\Models\CardDispute;
use App\Models\CardFundingQuote;
use App\Models\CardFundingRequest;
use App\Models\CardTransaction;
use App\Models\CardUnloadRequest;
use App\Models\CardWebhookEvent;
use App\Models\User;
use App\Services\BalanceProjectionService;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CardService
{
    public function __construct(
        private readonly CardProviderRegistry $providers,
        private readonly CardProductService $products,
        private readonly CardEligibilityService $eligibility,
        private readonly CardQuoteService $quotes,
        private readonly CardSettlementService $settlement,
        private readonly LedgerService $ledger,
        private readonly ReservationService $reservations,
        private readonly BalanceProjectionService $balances,
        private readonly CardAuditService $audit,
        private readonly CardRealtimeService $realtime,
        private readonly CardNotificationService $notifications,
        private readonly CardOperationsAlertService $alerts,
    ) {
    }

    public function products(): array
    {
        return [
            'products' => $this->products->all(),
            'provider' => $this->providers->provider()->health(),
        ];
    }

    public function list(User $user): array
    {
        return Card::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Card $card): array => $this->presentCard($card))
            ->all();
    }

    public function issue(User $user, string $productCode, string $idempotencyKey, ?string $nickname = null): Card
    {
        return DB::transaction(function () use ($idempotencyKey, $nickname, $productCode, $user): Card {
            $existing = Card::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) {
                    throw new RuntimeException('Idempotency key belongs to another card request.');
                }
                return $existing;
            }

            $decision = $this->eligibility->assertIssueAllowed($user, $productCode);
            $product = $decision['product'];
            $provider = $this->providers->provider((string) ($product['provider'] ?? null));

            $customer = CardCustomer::query()->where('user_id', $user->id)->where('provider', $provider->name())->lockForUpdate()->first();
            if (! $customer) {
                $providerCustomer = $provider->createCustomer([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'country' => $decision['country'],
                    'kyc_status' => (int) ($user->kyc_level ?? 0) >= 1 ? 'VERIFIED' : 'PENDING',
                ]);
                $customer = CardCustomer::query()->create([
                    'customer_uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'provider' => $provider->name(),
                    'provider_customer_id' => $providerCustomer['provider_customer_id'],
                    'provider_status' => $providerCustomer['provider_status'] ?? 'ACTIVE',
                    'kyc_status' => $providerCustomer['kyc_status'] ?? 'VERIFIED',
                    'country' => $decision['country'],
                    'metadata' => ['source' => 'exacard_issue'],
                ]);
            }

            $providerCard = $provider->issueCard($customer, $product);
            $card = Card::query()->create([
                'card_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'card_customer_id' => $customer->id,
                'provider' => $provider->name(),
                'provider_card_id' => $providerCard['provider_card_id'] ?? null,
                'card_product' => $product['product_code'],
                'type' => $product['type'],
                'currency' => strtoupper((string) $product['currency']),
                'network' => $providerCard['network'] ?? null,
                'last_four' => $providerCard['last_four'] ?? null,
                'expiry_month' => $providerCard['expiry_month'] ?? null,
                'expiry_year' => $providerCard['expiry_year'] ?? null,
                'status' => $providerCard['status'] ?? 'PENDING',
                'nickname' => $nickname,
                'provider_status' => $providerCard['provider_status'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'controls' => ['online' => true, 'international' => false, 'atm' => false],
                'limits' => ['daily' => null, 'monthly' => null, 'per_transaction' => null],
                'metadata' => ['production_issuance_enabled' => (bool) config('exacard.production_issuance_enabled', false)],
            ]);

            $this->audit->record($user, 'CARD_ISSUED', 'card', $card->id, ['product_code' => $product['product_code']], $user);
            $this->realtime->publishUser($user->id, 'card.created', ['card' => $this->presentCard($card)], (string) $card->card_uuid);
            $this->notifications->cardCreated($user, (string) $card->card_uuid);

            return $card;
        });
    }

    public function quoteFunding(User $user, string $cardUuid, string $sourceAsset, string $amount): CardFundingQuote
    {
        $card = $this->userCard($user, $cardUuid);
        $this->eligibility->assertCardActionAllowed($user, $card, 'FUND');
        $this->assertActive($card);

        $quote = $this->quotes->createFundingQuote($user, $card, $sourceAsset, $amount);
        $this->realtime->publishUser($user->id, 'card.funding.quoted', [
            'quote_uuid' => $quote->quote_uuid,
            'card_uuid' => $card->card_uuid,
            'amount' => (string) $quote->card_amount,
            'currency' => (string) $quote->card_currency,
            'expires_at' => $quote->expires_at?->toISOString(),
        ], (string) $quote->quote_uuid);

        return $quote;
    }

    public function fund(User $user, string $quoteUuid, string $idempotencyKey, array $context = []): CardFundingRequest
    {
        return DB::transaction(function () use ($context, $idempotencyKey, $quoteUuid, $user): CardFundingRequest {
            $existing = CardFundingRequest::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) {
                    throw new RuntimeException('Idempotency key belongs to another funding request.');
                }
                return $existing;
            }

            $quote = CardFundingQuote::query()->where('quote_uuid', $quoteUuid)->lockForUpdate()->firstOrFail();
            if ((int) $quote->user_id !== (int) $user->id || $quote->status !== 'QUOTED' || now()->gte($quote->expires_at)) {
                throw new RuntimeException('Funding quote is not available.');
            }
            $card = $this->userCard($user, (string) $quote->card->card_uuid);
            $this->eligibility->assertCardActionAllowed($user, $card, 'FUND');

            $reservation = $this->reservations->reserveUserAccount(
                $user->id,
                'funding',
                (string) $quote->source_asset,
                (string) $quote->total_debit,
                'EXACARD_FUNDING',
                'card_funding_quote',
                (string) $quote->quote_uuid,
                'card-funding:'.$idempotencyKey,
                ['quote_uuid' => $quote->quote_uuid, 'card_uuid' => $card->card_uuid],
            );

            $funding = CardFundingRequest::query()->create([
                'funding_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'card_id' => $card->id,
                'card_funding_quote_id' => $quote->id,
                'source_asset' => $quote->source_asset,
                'card_currency' => $quote->card_currency,
                'source_amount' => $quote->source_amount,
                'card_amount' => $quote->card_amount,
                'fee_amount' => FinancialDecimal::add((string) $quote->conversion_fee, (string) $quote->card_fee),
                'provider_fee' => $quote->provider_fee,
                'provider_cost' => $quote->provider_cost,
                'total_debit' => $quote->total_debit,
                'status' => 'RESERVED',
                'reservation_id' => $reservation->reservation_id,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['pricing_snapshot' => $quote->pricing_snapshot],
            ]);
            $this->realtime->publishUser($user->id, 'card.funding.processing', [
                'funding_uuid' => $funding->funding_uuid,
                'card_uuid' => $card->card_uuid,
                'status' => $funding->status,
                'amount' => (string) $funding->card_amount,
                'currency' => (string) $funding->card_currency,
            ], (string) $funding->funding_uuid);

            $providerResult = $this->providers->provider((string) $card->provider)->fundCard($card, array_merge($context, [
                'amount' => (string) $funding->card_amount,
                'currency' => (string) $funding->card_currency,
                'funding_uuid' => $funding->funding_uuid,
            ]));

            $funding->forceFill([
                'status' => $providerResult['status'],
                'provider_reference' => $providerResult['provider_reference'] ?? null,
                'metadata' => array_merge($funding->metadata ?? [], ['provider_result' => $providerResult]),
            ])->save();

            $quote->forceFill(['status' => 'CONSUMED', 'consumed_at' => now()])->save();

            if ($providerResult['status'] === 'COMPLETED') {
                $this->settlement->settleFunding($funding->fresh());
                $settled = $funding->fresh();
                $this->realtime->publishUser($user->id, 'card.funding.completed', [
                    'funding_uuid' => $settled->funding_uuid,
                    'card_uuid' => $card->card_uuid,
                    'status' => $settled->status,
                    'amount' => (string) $settled->card_amount,
                    'currency' => (string) $settled->card_currency,
                    'ledger_reference' => $settled->ledger_reference,
                ], (string) $settled->funding_uuid);
                $this->notifications->fundingCompleted($user, (string) $settled->funding_uuid, (string) $settled->card_amount, (string) $settled->card_currency);
                return $funding->fresh();
            }

            if ($providerResult['status'] === 'FAILED') {
                $this->reservations->release((string) $reservation->reservation_id, null, ['reason' => 'provider_failed']);
                $funding->forceFill(['status' => 'FAILED'])->save();
                $this->realtime->publishUser($user->id, 'card.funding.failed', [
                    'funding_uuid' => $funding->funding_uuid,
                    'card_uuid' => $card->card_uuid,
                    'status' => 'FAILED',
                    'reason' => $providerResult['reason'] ?? 'PROVIDER_FAILED',
                ], (string) $funding->funding_uuid);
                $this->notifications->fundingFailed($user, (string) $funding->funding_uuid);
            }

            if ($providerResult['status'] === 'PROVIDER_UNKNOWN') {
                $this->realtime->publishUser($user->id, 'card.funding.provider_pending', [
                    'funding_uuid' => $funding->funding_uuid,
                    'card_uuid' => $card->card_uuid,
                    'status' => 'PROVIDER_UNKNOWN',
                    'reason' => $providerResult['reason'] ?? 'PROVIDER_UNKNOWN',
                ], (string) $funding->funding_uuid);
                $this->notifications->fundingUnknown($user, (string) $funding->funding_uuid);
            }

            return $funding->fresh();
        });
    }

    public function unload(User $user, string $cardUuid, string $amount, string $idempotencyKey): CardUnloadRequest
    {
        return DB::transaction(function () use ($amount, $cardUuid, $idempotencyKey, $user): CardUnloadRequest {
            $existing = CardUnloadRequest::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) {
                    throw new RuntimeException('Idempotency key belongs to another unload request.');
                }
                return $existing;
            }

            $card = $this->userCard($user, $cardUuid);
            $this->eligibility->assertCardActionAllowed($user, $card, 'UNLOAD');
            $asset = strtoupper((string) $card->currency);
            $normalized = FinancialDecimal::normalize($amount);
            $cardAccount = $this->ledger->getOrCreateAccount($user->id, 'exacard', $asset);
            $available = $this->balances->accountProjection($cardAccount)['available'];
            if (FinancialDecimal::compare($available, $normalized) < 0) {
                throw new RuntimeException('Insufficient card balance.');
            }

            $unload = CardUnloadRequest::query()->create([
                'unload_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'card_id' => $card->id,
                'asset' => $asset,
                'amount' => $normalized,
                'fee_amount' => '0',
                'net_amount' => $normalized,
                'status' => 'PROCESSING',
                'idempotency_key' => $idempotencyKey,
                'metadata' => [],
            ]);

            $providerResult = $this->providers->provider((string) $card->provider)->unloadCard($card, ['amount' => $normalized, 'currency' => $asset, 'unload_uuid' => $unload->unload_uuid]);
            $unload->forceFill(['status' => $providerResult['status'], 'provider_reference' => $providerResult['provider_reference'] ?? null])->save();
            $this->realtime->publishUser($user->id, 'card.unload.processing', [
                'unload_uuid' => $unload->unload_uuid,
                'card_uuid' => $card->card_uuid,
                'status' => $unload->status,
                'amount' => (string) $unload->amount,
                'currency' => $asset,
            ], (string) $unload->unload_uuid);
            if ($providerResult['status'] === 'COMPLETED') {
                $this->settlement->settleUnload($unload->fresh());
                $settled = $unload->fresh();
                $this->realtime->publishUser($user->id, 'card.unload.completed', [
                    'unload_uuid' => $settled->unload_uuid,
                    'card_uuid' => $card->card_uuid,
                    'status' => $settled->status,
                    'amount' => (string) $settled->amount,
                    'currency' => $asset,
                    'ledger_reference' => $settled->ledger_reference,
                ], (string) $settled->unload_uuid);
            }

            return $unload->fresh();
        });
    }

    public function transactions(User $user, string $cardUuid): array
    {
        $card = $this->userCard($user, $cardUuid);

        return CardTransaction::query()
            ->where('user_id', $user->id)
            ->where('card_id', $card->id)
            ->latest('provider_created_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CardTransaction $transaction): array => [
                'transaction_uuid' => $transaction->transaction_uuid,
                'type' => $transaction->type,
                'merchant' => $transaction->merchant,
                'mcc' => $transaction->mcc,
                'country' => $transaction->country,
                'transaction_currency' => $transaction->transaction_currency,
                'transaction_amount' => (string) $transaction->transaction_amount,
                'billing_currency' => $transaction->billing_currency,
                'billing_amount' => (string) $transaction->billing_amount,
                'fee' => (string) $transaction->fee,
                'status' => $transaction->status,
                'created_at' => $transaction->provider_created_at?->toISOString() ?? $transaction->created_at?->toISOString(),
            ])
            ->all();
    }

    public function authorizations(User $user, string $cardUuid): array
    {
        $card = $this->userCard($user, $cardUuid);

        return CardAuthorization::query()
            ->where('user_id', $user->id)
            ->where('card_id', $card->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (CardAuthorization $authorization): array => [
                'authorization_uuid' => $authorization->authorization_uuid,
                'amount' => (string) $authorization->amount,
                'currency' => $authorization->currency,
                'merchant' => $authorization->merchant,
                'status' => $authorization->status,
                'created_at' => $authorization->created_at?->toISOString(),
            ])
            ->all();
    }

    public function updateStatus(User $user, string $cardUuid, string $action, string $reason): Card
    {
        $card = $this->userCard($user, $cardUuid);
        $provider = $this->providers->provider((string) $card->provider);
        $result = strtoupper($action) === 'FREEZE' ? $provider->freeze($card, $reason) : $provider->unfreeze($card, $reason);
        $card->forceFill(['status' => $result['status'], 'metadata' => array_merge($card->metadata ?? [], ['last_status_reason' => $reason])])->save();
        $this->audit->record($user, 'CARD_'.strtoupper($action), 'card', $card->id, ['reason' => $reason], $user);
        $fresh = $card->fresh();
        $eventType = strtoupper($action) === 'FREEZE' ? 'card.frozen' : 'card.unfrozen';
        $this->realtime->publishUser($user->id, $eventType, ['card' => $this->presentCard($fresh), 'reason' => $reason], (string) $fresh->card_uuid);
        $this->notifications->cardStatus($user, (string) $fresh->card_uuid, (string) $fresh->status);

        return $fresh;
    }

    public function reportLostOrStolen(User $user, string $cardUuid, string $reason): Card
    {
        $card = $this->userCard($user, $cardUuid);
        $result = $this->providers->provider((string) $card->provider)->freeze($card, $reason);
        $card->forceFill([
            'status' => 'BLOCKED',
            'metadata' => array_merge($card->metadata ?? [], [
                'lost_or_stolen_reported_at' => now()->toISOString(),
                'lost_or_stolen_reason' => $reason,
                'provider_result' => $result,
            ]),
        ])->save();
        $this->audit->record($user, 'CARD_LOST_OR_STOLEN_REPORTED', 'card', $card->id, ['reason' => $reason], $user);
        $fresh = $card->fresh();
        $this->realtime->publishUser($user->id, 'card.blocked', ['card' => $this->presentCard($fresh), 'reason' => $reason], (string) $fresh->card_uuid);
        $this->notifications->cardStatus($user, (string) $fresh->card_uuid, 'BLOCKED');

        return $fresh;
    }

    public function terminate(User $user, string $cardUuid, string $reason): Card
    {
        return DB::transaction(function () use ($cardUuid, $reason, $user): Card {
            $card = $this->userCard($user, $cardUuid);
            $blockers = $this->terminationBlockers($user, $card);
            if ($blockers !== []) {
                throw new RuntimeException('Card cannot be terminated while it has unresolved balance, authorizations, funding, unloads, or disputes.');
            }

            $result = $this->providers->provider((string) $card->provider)->terminate($card, $reason);
            $card->forceFill([
                'status' => $result['status'],
                'metadata' => array_merge($card->metadata ?? [], [
                    'terminated_at' => now()->toISOString(),
                    'termination_reason' => $reason,
                    'provider_result' => $result,
                ]),
            ])->save();
            $this->audit->record($user, 'CARD_TERMINATED', 'card', $card->id, ['reason' => $reason], $user);
            $fresh = $card->fresh();
            $this->realtime->publishUser($user->id, 'card.terminated', ['card' => $this->presentCard($fresh), 'reason' => $reason], (string) $fresh->card_uuid);
            $this->notifications->cardStatus($user, (string) $fresh->card_uuid, 'TERMINATED');

            return $fresh;
        });
    }

    public function accountClosureBlockers(User $user): array
    {
        return Card::query()
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (Card $card): array => [$card->card_uuid => $this->terminationBlockers($user, $card)])
            ->filter(fn (array $blockers): bool => $blockers !== [])
            ->all();
    }

    public function updateControls(User $user, string $cardUuid, array $controls): Card
    {
        $card = $this->userCard($user, $cardUuid);
        $this->providers->provider((string) $card->provider)->updateControls($card, $controls);
        $card->forceFill(['controls' => array_merge($card->controls ?? [], $controls)])->save();
        $this->audit->record($user, 'CARD_CONTROLS_UPDATED', 'card', $card->id, ['controls' => $controls], $user);
        $fresh = $card->fresh();
        $this->realtime->publishUser($user->id, 'card.control.updated', ['card' => $this->presentCard($fresh), 'controls' => $controls], (string) $fresh->card_uuid);

        return $fresh;
    }

    public function updateLimits(User $user, string $cardUuid, array $limits): Card
    {
        $card = $this->userCard($user, $cardUuid);
        $this->providers->provider((string) $card->provider)->updateLimits($card, $limits);
        $card->forceFill(['limits' => array_merge($card->limits ?? [], $limits)])->save();
        $this->audit->record($user, 'CARD_LIMITS_UPDATED', 'card', $card->id, ['limits' => $limits], $user);
        $fresh = $card->fresh();
        $this->realtime->publishUser($user->id, 'card.limit.updated', ['card' => $this->presentCard($fresh), 'limits' => $limits], (string) $fresh->card_uuid);

        return $fresh;
    }

    public function sensitiveDetailsToken(User $user, string $cardUuid): array
    {
        $card = $this->userCard($user, $cardUuid);
        $this->eligibility->assertCardActionAllowed($user, $card, 'VIEW_DETAILS');
        $token = $this->providers->provider((string) $card->provider)->sensitiveDetailsToken($card);
        $this->audit->record($user, 'CARD_DETAILS_TOKEN_CREATED', 'card', $card->id, [], $user);

        return $token;
    }

    public function handleWebhook(string $providerName, string $rawBody, array $headers): CardWebhookEvent
    {
        $provider = $this->providers->provider($providerName);
        if (! $provider->verifyWebhook($rawBody, $headers)) {
            $this->alerts->webhookFailure($providerName, 'INVALID_SIGNATURE');
            throw new RuntimeException('Invalid card webhook signature.');
        }

        $parsed = $provider->parseWebhook($rawBody, $headers);

        return DB::transaction(function () use ($parsed, $providerName): CardWebhookEvent {
            $event = CardWebhookEvent::query()->firstOrCreate([
                'provider' => $providerName,
                'provider_event_id' => $parsed['event_id'],
            ], [
                'event_uuid' => (string) Str::uuid(),
                'event_type' => $parsed['event_type'],
                'status' => 'RECEIVED',
                'payload' => $parsed['payload'],
                'headers' => [],
            ]);

            if ($event->status === 'PROCESSED') {
                return $event;
            }

            $payload = $parsed['payload'];
            $card = $this->findProviderCard($providerName, (string) ($payload['provider_card_id'] ?? $payload['card_id'] ?? ''));
            if ($card) {
                $this->applyWebhookToCard($card, $parsed['event_type'], $payload);
            }

            $event->forceFill(['status' => 'PROCESSED', 'processed_at' => now()])->save();
            return $event->fresh();
        });
    }

    public function presentCard(Card $card): array
    {
        $balance = $this->balances->byUserAccountAndAsset((int) $card->user_id, 'exacard', (string) $card->currency);

        return [
            'card_uuid' => $card->card_uuid,
            'product' => $card->card_product,
            'type' => $card->type,
            'currency' => $card->currency,
            'network' => $card->network,
            'last_four' => $card->last_four,
            'expiry_month' => $card->expiry_month,
            'expiry_year' => $card->expiry_year,
            'status' => $card->status,
            'nickname' => $card->nickname,
            'controls' => $card->controls ?? [],
            'limits' => $card->limits ?? [],
            'balance' => $balance,
            'provider' => $card->provider,
        ];
    }

    public function userCard(User $user, string $cardUuid): Card
    {
        return Card::query()->where('user_id', $user->id)->where('card_uuid', $cardUuid)->firstOrFail();
    }

    private function assertActive(Card $card): void
    {
        if (! in_array($card->status, ['ACTIVE'], true)) {
            throw new RuntimeException('Card is not active.');
        }
    }

    private function findProviderCard(string $provider, string $providerCardId): ?Card
    {
        if ($providerCardId === '') {
            return null;
        }

        return Card::query()->where('provider', $provider)->where('provider_card_id', $providerCardId)->first();
    }

    private function applyWebhookToCard(Card $card, string $eventType, array $payload): void
    {
        $user = User::query()->find((int) $card->user_id);
        if (in_array($eventType, ['CARD_STATUS_UPDATED', 'CARD.FROZEN', 'CARD.UNFROZEN'], true)) {
            $card->forceFill(['status' => strtoupper((string) ($payload['status'] ?? $card->status))])->save();
            $fresh = $card->fresh();
            $type = match ((string) $fresh->status) {
                'FROZEN' => 'card.frozen',
                'ACTIVE' => 'card.unfrozen',
                'BLOCKED' => 'card.blocked',
                default => 'card.status.changed',
            };
            $this->realtime->publishUser((int) $fresh->user_id, $type, ['card' => $this->presentCard($fresh)], (string) $fresh->card_uuid);
            if ($user) {
                $this->notifications->cardStatus($user, (string) $fresh->card_uuid, (string) $fresh->status);
            }
        }

        if (str_starts_with($eventType, 'AUTHORIZATION')) {
            $authorization = CardAuthorization::query()->updateOrCreate([
                'provider' => (string) $card->provider,
                'provider_authorization_id' => (string) ($payload['authorization_id'] ?? $payload['provider_authorization_id'] ?? Str::uuid()),
            ], [
                'authorization_uuid' => (string) ($payload['authorization_uuid'] ?? Str::uuid()),
                'card_id' => $card->id,
                'user_id' => $card->user_id,
                'amount' => (string) ($payload['amount'] ?? '0'),
                'currency' => strtoupper((string) ($payload['currency'] ?? $card->currency)),
                'merchant' => $payload['merchant'] ?? null,
                'status' => strtoupper((string) ($payload['status'] ?? 'AUTHORIZED')),
                'metadata' => $payload,
            ]);
            $this->realtime->publishUser((int) $card->user_id, 'card.authorization.updated', [
                'authorization_uuid' => $authorization->authorization_uuid,
                'card_uuid' => $card->card_uuid,
                'merchant' => $authorization->merchant,
                'amount' => (string) $authorization->amount,
                'currency' => $authorization->currency,
                'status' => $authorization->status,
            ], (string) $authorization->authorization_uuid);
            if ($user) {
                $this->notifications->purchase($user, (string) $authorization->authorization_uuid, (string) $authorization->status, $authorization->merchant, (string) $authorization->amount, (string) $authorization->currency, $card->last_four);
            }
        }

        if (in_array($eventType, ['TRANSACTION.CAPTURED', 'TRANSACTION.REFUNDED', 'TRANSACTION.REVERSED', 'CHARGEBACK.CREATED'], true)) {
            $transaction = CardTransaction::query()->updateOrCreate([
                'provider' => (string) $card->provider,
                'provider_transaction_id' => (string) ($payload['transaction_id'] ?? $payload['provider_transaction_id'] ?? Str::uuid()),
            ], [
                'transaction_uuid' => (string) ($payload['transaction_uuid'] ?? Str::uuid()),
                'card_id' => $card->id,
                'user_id' => $card->user_id,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'type' => $eventType,
                'merchant' => $payload['merchant'] ?? null,
                'mcc' => $payload['mcc'] ?? null,
                'country' => $payload['country'] ?? null,
                'transaction_currency' => strtoupper((string) ($payload['transaction_currency'] ?? $card->currency)),
                'transaction_amount' => (string) ($payload['transaction_amount'] ?? $payload['amount'] ?? '0'),
                'billing_currency' => strtoupper((string) ($payload['billing_currency'] ?? $card->currency)),
                'billing_amount' => (string) ($payload['billing_amount'] ?? $payload['amount'] ?? '0'),
                'fee' => (string) ($payload['fee'] ?? '0'),
                'provider_cost' => (string) ($payload['provider_cost'] ?? '0'),
                'fx_rate' => (string) ($payload['fx_rate'] ?? '1'),
                'authorization_reference' => $payload['authorization_reference'] ?? null,
                'status' => strtoupper((string) ($payload['status'] ?? 'POSTED')),
                'provider_created_at' => isset($payload['created_at']) ? \Carbon\Carbon::parse($payload['created_at']) : now(),
                'metadata' => $payload,
            ]);
            $publicEventType = match ($eventType) {
                'TRANSACTION.REFUNDED' => 'card.refund.completed',
                'TRANSACTION.REVERSED' => 'card.transaction.reversed',
                'CHARGEBACK.CREATED' => 'card.chargeback.created',
                default => 'card.transaction.completed',
            };
            $this->realtime->publishUser((int) $card->user_id, $publicEventType, [
                'transaction_uuid' => $transaction->transaction_uuid,
                'card_uuid' => $card->card_uuid,
                'merchant' => $transaction->merchant,
                'amount' => (string) $transaction->billing_amount,
                'currency' => $transaction->billing_currency,
                'status' => $transaction->status,
                'type' => $transaction->type,
            ], (string) $transaction->transaction_uuid);

            if ($user) {
                if ($eventType === 'TRANSACTION.REFUNDED') {
                    $this->notifications->refund($user, (string) $transaction->transaction_uuid, (string) $transaction->billing_amount, (string) $transaction->billing_currency);
                } elseif ($eventType !== 'CHARGEBACK.CREATED') {
                    $this->notifications->purchase($user, (string) $transaction->transaction_uuid, (string) $transaction->status, $transaction->merchant, (string) $transaction->billing_amount, (string) $transaction->billing_currency, $card->last_four);
                }
            }

            if ($eventType === 'CHARGEBACK.CREATED') {
                $dispute = CardDispute::query()->updateOrCreate([
                    'provider_dispute_id' => (string) ($payload['dispute_id'] ?? $payload['provider_dispute_id'] ?? $payload['transaction_id'] ?? Str::uuid()),
                ], [
                    'dispute_uuid' => (string) ($payload['dispute_uuid'] ?? Str::uuid()),
                    'card_id' => $card->id,
                    'user_id' => $card->user_id,
                    'card_transaction_id' => $transaction->id,
                    'status' => strtoupper((string) ($payload['dispute_status'] ?? 'OPEN')),
                    'amount' => (string) ($payload['amount'] ?? $payload['billing_amount'] ?? '0'),
                    'currency' => strtoupper((string) ($payload['currency'] ?? $payload['billing_currency'] ?? $card->currency)),
                    'evidence' => $payload['evidence'] ?? [],
                    'metadata' => $payload,
                ]);
                $this->realtime->publishUser((int) $card->user_id, 'card.chargeback.updated', [
                    'dispute_uuid' => $dispute->dispute_uuid,
                    'card_uuid' => $card->card_uuid,
                    'transaction_uuid' => $transaction->transaction_uuid,
                    'status' => $dispute->status,
                    'amount' => (string) $dispute->amount,
                    'currency' => $dispute->currency,
                ], (string) $dispute->dispute_uuid);
                if ($user) {
                    $this->notifications->dispute($user, (string) $dispute->dispute_uuid);
                }
            }
        }
    }

    private function terminationBlockers(User $user, Card $card): array
    {
        $balance = $this->balances->byUserAccountAndAsset((int) $user->id, 'exacard', (string) $card->currency);
        $blockers = [];
        if (FinancialDecimal::compare((string) ($balance['total'] ?? '0'), '0') > 0) {
            $blockers[] = 'CARD_BALANCE_REMAINING';
        }
        if (CardAuthorization::query()->where('card_id', $card->id)->whereIn('status', ['AUTHORIZED', 'PENDING'])->exists()) {
            $blockers[] = 'OPEN_AUTHORIZATION';
        }
        if (CardFundingRequest::query()->where('card_id', $card->id)->whereIn('status', ['RESERVED', 'PENDING_PROVIDER', 'PROVIDER_UNKNOWN'])->exists()) {
            $blockers[] = 'PENDING_FUNDING';
        }
        if (CardUnloadRequest::query()->where('card_id', $card->id)->whereIn('status', ['PROCESSING', 'PENDING_PROVIDER', 'PROVIDER_UNKNOWN'])->exists()) {
            $blockers[] = 'PENDING_UNLOAD';
        }
        if (CardDispute::query()->where('card_id', $card->id)->whereIn('status', ['OPEN', 'REVIEWING', 'ESCALATED'])->exists()) {
            $blockers[] = 'OPEN_DISPUTE';
        }

        return $blockers;
    }
}
