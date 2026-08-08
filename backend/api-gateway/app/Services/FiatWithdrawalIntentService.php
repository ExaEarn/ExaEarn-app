<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\FiatWithdrawalBeneficiary;
use App\Models\FiatWithdrawalIntent;
use App\Models\FiatWithdrawalProviderEvent;
use App\Models\FiatWithdrawalVerificationChallenge;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\Withdrawal;
use App\Services\FiatPayout\FiatPayoutProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class FiatWithdrawalIntentService
{
    private const SCALE = 8;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FiatPayoutProviderManager $providers,
    ) {
    }

    public function meta(User $user, string $currency = 'USD'): array
    {
        $currency = strtoupper($currency);
        $this->seedFundingLedgerFromLegacyIfNeeded((int) $user->id, $currency);
        $balance = $this->fundingBalance((int) $user->id, $currency);
        $pending = FiatWithdrawalIntent::query()
            ->where('user_id', $user->id)
            ->where('currency', $currency)
            ->whereIn('status', ['awaiting_verification', 'submitted', 'processing', 'under_review'])
            ->sum('amount');

        $dailyLimit = $this->fmt((string) config('wallet.fiat_withdrawals.daily_limit', '25000'));
        $withdrawnToday = FiatWithdrawalIntent::query()
            ->where('user_id', $user->id)
            ->where('currency', $currency)
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('status', ['failed', 'cancelled', 'reversed'])
            ->sum('amount');
        $remaining = $this->max('0', $this->sub($dailyLimit, $this->fmt((string) $withdrawnToday)));

        return [
            'balance' => [
                'currency' => $currency,
                'available' => $balance,
                'local_equivalent' => $balance,
                'local_currency' => $currency,
                'pending_withdrawals' => $this->fmt((string) $pending),
                'daily_limit' => $dailyLimit,
                'remaining_daily_limit' => $remaining,
            ],
            'source_accounts' => [
                ['key' => 'funding', 'label' => 'Funding Wallet', 'available' => $balance, 'currency' => $currency, 'supported' => true],
            ],
            'limits' => [
                'minimum' => $this->fmt((string) config('wallet.fiat_withdrawals.minimum', '10')),
                'maximum' => $this->fmt((string) config('wallet.fiat_withdrawals.maximum', '10000')),
                'daily' => $dailyLimit,
                'remaining_daily' => $remaining,
            ],
            'verification_methods' => [
                ['key' => 'authenticator', 'label' => 'Authenticator App', 'enabled' => (bool) $user->two_factor_enabled],
                ['key' => 'email', 'label' => 'Email OTP', 'enabled' => true],
                ['key' => 'sms', 'label' => 'SMS OTP', 'enabled' => false],
            ],
            'default_currency' => $currency,
            'supported_currencies' => collect((array) config('swap.supported_fiat', ['NGN', 'USD']))->map(fn (string $item): string => strtoupper($item))->unique()->values()->all(),
            'countries' => [
                ['code' => 'NG', 'name' => 'Nigeria', 'default_currency' => 'NGN'],
                ['code' => 'US', 'name' => 'United States', 'default_currency' => 'USD'],
            ],
        ];
    }

    public function banks(string $country, string $currency): array
    {
        return $this->providers->provider()->banks(strtoupper($country), strtoupper($currency));
    }

    public function resolveAccount(User $user, array $payload): array
    {
        $country = strtoupper((string) $payload['country']);
        $currency = strtoupper((string) $payload['currency']);
        $bankCode = (string) $payload['bank_code'];
        $accountNumber = preg_replace('/\D+/', '', (string) $payload['account_number']);
        $provider = $this->providers->provider();
        $resolved = $provider->resolveAccount($country, $currency, $bankCode, $accountNumber);

        return [
            'country' => $country,
            'currency' => $currency,
            'provider' => $provider->key(),
            'bank' => ['code' => $bankCode, 'name' => $this->bankName($country, $currency, $bankCode)],
            'account_number' => $accountNumber,
            'masked_account_number' => $this->maskAccount($accountNumber),
            'account_name' => $resolved['account_name'],
            'verified' => true,
        ];
    }

    public function quote(User $user, array $payload): array
    {
        $currency = strtoupper((string) $payload['currency']);
        $amount = $this->fmt((string) $payload['amount']);
        $sourceAccount = (string) ($payload['source_account'] ?? 'funding');
        if ($sourceAccount !== 'funding') {
            throw new RuntimeException('Fiat withdrawals are currently supported from Funding Wallet only.');
        }

        $this->validateAmount($user, $currency, $amount);
        $fee = $this->fee($amount, $currency);
        $receives = $this->sub($amount, $fee);
        if ($this->compare($receives, '0') <= 0) {
            throw new RuntimeException('Withdrawal amount must be greater than the fee.');
        }

        $available = $this->fundingBalance((int) $user->id, $currency);

        return [
            'currency' => $currency,
            'source_account' => $sourceAccount,
            'amount' => $amount,
            'fee' => $fee,
            'recipient_receives' => $receives,
            'available_balance' => $available,
            'remaining_balance_after' => $this->sub($available, $amount),
            'estimated_arrival' => '5-15 minutes',
            'minimum' => $this->fmt((string) config('wallet.fiat_withdrawals.minimum', '10')),
            'maximum' => $this->fmt((string) config('wallet.fiat_withdrawals.maximum', '10000')),
            'daily_remaining_limit' => $this->meta($user, $currency)['limits']['remaining_daily'],
            'quote_expires_at' => now()->addMinutes(5)->toISOString(),
        ];
    }

    public function beneficiaries(User $user, ?string $currency = null): array
    {
        return FiatWithdrawalBeneficiary::query()
            ->where('user_id', $user->id)
            ->when($currency, fn ($query) => $query->where('currency', strtoupper($currency)))
            ->where('status', 'active')
            ->latest('is_default')
            ->latest('id')
            ->get()
            ->map(fn (FiatWithdrawalBeneficiary $beneficiary): array => $this->presentBeneficiary($beneficiary))
            ->values()
            ->all();
    }

    public function saveBeneficiary(User $user, array $payload): FiatWithdrawalBeneficiary
    {
        return DB::transaction(function () use ($payload, $user): FiatWithdrawalBeneficiary {
            $currency = strtoupper((string) $payload['currency']);
            $provider = (string) ($payload['provider'] ?? $this->providers->provider()->key());
            if (!empty($payload['is_default'])) {
                FiatWithdrawalBeneficiary::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('provider', $provider)
                    ->update(['is_default' => false]);
            }

            return FiatWithdrawalBeneficiary::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'currency' => $currency,
                    'bank_code' => (string) $payload['bank_code'],
                    'account_number' => preg_replace('/\D+/', '', (string) $payload['account_number']),
                ],
                [
                    'country' => strtoupper((string) $payload['country']),
                    'provider' => $provider,
                    'bank_name' => (string) $payload['bank_name'],
                    'account_name' => (string) $payload['account_name'],
                    'masked_account_number' => $this->maskAccount((string) $payload['account_number']),
                    'is_default' => (bool) ($payload['is_default'] ?? false),
                    'status' => 'active',
                    'metadata' => ['source' => 'fiat_withdrawal'],
                ],
            );
        });
    }

    public function deleteBeneficiary(User $user, int $id): void
    {
        $beneficiary = FiatWithdrawalBeneficiary::query()->where('user_id', $user->id)->findOrFail($id);
        $beneficiary->update(['status' => 'deleted', 'is_default' => false]);
    }

    public function createIntent(User $user, array $payload, ?string $idempotencyKey = null): FiatWithdrawalIntent
    {
        if ($idempotencyKey) {
            $existing = FiatWithdrawalIntent::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $currency = strtoupper((string) $payload['currency']);
        $amount = $this->fmt((string) $payload['amount']);
        $quote = $this->quote($user, ['currency' => $currency, 'amount' => $amount, 'source_account' => $payload['source_account'] ?? 'funding']);

        $beneficiary = null;
        if (!empty($payload['beneficiary_id'])) {
            $beneficiary = FiatWithdrawalBeneficiary::query()->where('user_id', $user->id)->where('status', 'active')->findOrFail((int) $payload['beneficiary_id']);
        }

        $accountNumber = preg_replace('/\D+/', '', (string) ($beneficiary?->account_number ?? $payload['account_number']));

        return FiatWithdrawalIntent::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'beneficiary_id' => $beneficiary?->id,
            'reference' => 'FWD-' . strtoupper(Str::random(14)),
            'idempotency_key' => $idempotencyKey,
            'source_account' => (string) ($payload['source_account'] ?? 'funding'),
            'country' => strtoupper((string) ($payload['country'] ?? $beneficiary?->country ?? 'NG')),
            'currency' => $currency,
            'amount' => $quote['amount'],
            'fee' => $quote['fee'],
            'recipient_receives' => $quote['recipient_receives'],
            'remaining_balance_after' => $quote['remaining_balance_after'],
            'provider' => (string) ($payload['provider'] ?? $beneficiary?->provider ?? $this->providers->provider()->key()),
            'bank_code' => (string) ($beneficiary?->bank_code ?? $payload['bank_code']),
            'bank_name' => (string) ($beneficiary?->bank_name ?? $payload['bank_name']),
            'account_number_last4' => substr($accountNumber, -4),
            'account_name' => (string) ($beneficiary?->account_name ?? $payload['account_name']),
            'narration' => trim((string) ($payload['narration'] ?? '')) ?: 'ExaEarn Withdrawal',
            'estimated_arrival' => $quote['estimated_arrival'],
            'status' => 'awaiting_verification',
            'quote_expires_at' => now()->addMinutes(5),
            'metadata' => ['account_number' => $accountNumber, 'masked_account_number' => $this->maskAccount($accountNumber), 'quote' => $quote],
        ]);
    }

    public function createVerificationChallenge(User $user, FiatWithdrawalIntent $intent, string $method): FiatWithdrawalVerificationChallenge
    {
        $this->authorizeIntent($user, $intent);
        if (!in_array($intent->status, ['awaiting_verification', 'under_review'], true)) {
            throw new RuntimeException('This withdrawal is not awaiting verification.');
        }

        $method = strtolower($method);
        if (!in_array($method, ['authenticator', 'email', 'sms'], true)) {
            throw new RuntimeException('Unsupported verification method.');
        }

        $code = (string) random_int(100000, 999999);

        return FiatWithdrawalVerificationChallenge::query()->create([
            'fiat_withdrawal_intent_id' => $intent->id,
            'user_id' => $user->id,
            'method' => $method,
            'code_hash' => Hash::make($code),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'metadata' => app()->isProduction() ? [] : ['development_code' => $code],
        ]);
    }

    public function verifyAndSubmit(User $user, FiatWithdrawalIntent $intent, array $payload): FiatWithdrawalIntent
    {
        $this->authorizeIntent($user, $intent);

        return DB::transaction(function () use ($intent, $payload, $user): FiatWithdrawalIntent {
            $intent = FiatWithdrawalIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if (!in_array($intent->status, ['awaiting_verification', 'under_review'], true)) {
                return $intent;
            }

            $challenge = FiatWithdrawalVerificationChallenge::query()
                ->where('fiat_withdrawal_intent_id', $intent->id)
                ->where('user_id', $user->id)
                ->where('method', strtolower((string) $payload['method']))
                ->where('status', 'pending')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$challenge || $challenge->expires_at->isPast()) {
                throw new RuntimeException('Verification code expired. Request a new code.');
            }

            if (!Hash::check((string) $payload['code'], $challenge->code_hash)) {
                $challenge->increment('attempts');
                throw new RuntimeException('Invalid verification code.');
            }

            $challenge->update(['status' => 'verified', 'verified_at' => now()]);
            $this->reserveFunds($user, $intent);

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id,
                'transaction_id' => null,
                'currency' => $intent->currency,
                'amount' => $intent->amount,
                'fee' => $intent->fee,
                'address' => 'bank:' . $intent->bank_name . ':' . $intent->account_number_last4,
                'network' => null,
                'tx_hash' => null,
                'status' => 'processing',
                'metadata' => [
                    'kind' => 'fiat',
                    'intent_uuid' => $intent->uuid,
                    'reference' => $intent->reference,
                    'bank_name' => $intent->bank_name,
                    'account_number' => $this->maskAccount((string) ($intent->metadata['account_number'] ?? $intent->account_number_last4)),
                    'account_name' => $intent->account_name,
                    'narration' => $intent->narration,
                    'fee' => (string) $intent->fee,
                    'recipient_receives' => (string) $intent->recipient_receives,
                ],
            ]);

            $result = $this->providers->provider($intent->provider)->submit($intent);
            $intent->update([
                'withdrawal_id' => $withdrawal->id,
                'status' => $this->normalizeProviderStatus($result['status'] ?? 'processing'),
                'provider_reference' => $result['provider_reference'] ?? null,
                'estimated_arrival' => $result['estimated_arrival'] ?? $intent->estimated_arrival,
                'submitted_at' => now(),
                'metadata' => array_merge($intent->metadata ?? [], ['provider_submit' => $result['raw'] ?? $result]),
            ]);

            return $intent->fresh();
        });
    }

    public function handleProviderEvent(string $providerKey, array $payload, array $headers = []): ?FiatWithdrawalIntent
    {
        $provider = $this->providers->provider($providerKey);
        $event = $provider->parseWebhook($payload, $headers);
        if (!$event['valid']) {
            throw new RuntimeException('Invalid fiat payout webhook signature.');
        }

        return DB::transaction(function () use ($event, $provider): ?FiatWithdrawalIntent {
            $reference = (string) ($event['reference'] ?? '');
            $intent = FiatWithdrawalIntent::query()
                ->where('reference', $reference)
                ->orWhere('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            FiatWithdrawalProviderEvent::query()->firstOrCreate(
                ['provider' => $provider->key(), 'event_id' => $event['event_id'] ?? null],
                [
                    'fiat_withdrawal_intent_id' => $intent?->id,
                    'event_type' => $event['event_type'] ?? 'fiat_withdrawal.status',
                    'status' => $event['status'] ?? null,
                    'payload' => $event['payload'],
                    'processed_at' => now(),
                ],
            );

            if (!$intent) {
                return null;
            }

            $status = $this->normalizeProviderStatus((string) ($event['status'] ?? 'processing'));
            if ($status === 'successful') {
                $this->settleSuccessful($intent);
            } elseif (in_array($status, ['failed', 'cancelled', 'reversed'], true)) {
                $this->reverseFailed($intent, $status);
            } else {
                $intent->update(['status' => $status]);
            }

            return $intent->fresh();
        });
    }

    public function history(User $user, int $limit = 50): array
    {
        return FiatWithdrawalIntent::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (FiatWithdrawalIntent $intent): array => $this->presentIntent($intent))
            ->values()
            ->all();
    }

    public function presentIntent(FiatWithdrawalIntent $intent): array
    {
        return [
            'id' => $intent->id,
            'uuid' => $intent->uuid,
            'reference' => $intent->reference,
            'source_account' => $intent->source_account,
            'currency' => $intent->currency,
            'amount' => (string) $intent->amount,
            'fee' => (string) $intent->fee,
            'recipient_receives' => (string) $intent->recipient_receives,
            'remaining_balance_after' => (string) $intent->remaining_balance_after,
            'recipient' => $intent->account_name,
            'bank' => $intent->bank_name,
            'masked_account_number' => $this->maskAccount((string) ($intent->metadata['account_number'] ?? $intent->account_number_last4)),
            'narration' => $intent->narration,
            'estimated_arrival' => $intent->estimated_arrival,
            'status' => $intent->status,
            'provider_reference' => $intent->provider_reference,
            'created_at' => optional($intent->created_at)?->toISOString(),
            'submitted_at' => optional($intent->submitted_at)?->toISOString(),
            'completed_at' => optional($intent->completed_at)?->toISOString(),
        ];
    }

    private function reserveFunds(User $user, FiatWithdrawalIntent $intent): void
    {
        if ($intent->reserve_ledger_reference) {
            return;
        }

        $reference = 'FWD-RES-' . $intent->reference;
        $funding = $this->ledger->getOrCreateAccount($user->id, 'funding', $intent->currency);
        if ($this->compare((string) $funding->balance, (string) $intent->amount) < 0) {
            throw new RuntimeException('Insufficient Funding Wallet balance.');
        }

        $reserve = $this->ledger->getOrCreateAccount(null, 'fiat_withdrawal_reserve', $intent->currency);
        $this->ledger->postDoubleEntry($reference, 'Fiat withdrawal reserve', [
            ['account_id' => $funding->id, 'amount' => $this->sub('0', (string) $intent->amount), 'asset' => $intent->currency, 'user_id' => $user->id],
            ['account_id' => $reserve->id, 'amount' => (string) $intent->amount, 'asset' => $intent->currency],
        ], 'fiat_withdrawal_reserve', ['intent_uuid' => $intent->uuid]);

        $intent->update(['reserve_ledger_reference' => $reference, 'status' => 'submitted']);
    }

    private function settleSuccessful(FiatWithdrawalIntent $intent): void
    {
        if ($intent->settlement_ledger_reference) {
            $intent->update(['status' => 'successful', 'completed_at' => now()]);
            return;
        }

        $reference = 'FWD-SET-' . $intent->reference;
        $reserve = $this->ledger->getOrCreateAccount(null, 'fiat_withdrawal_reserve', $intent->currency);
        $external = $this->ledger->getOrCreateAccount(null, 'external_fiat_payout', $intent->currency);
        $treasury = $this->ledger->getOrCreateAccount(null, 'system_treasury', $intent->currency);
        $this->ledger->postDoubleEntry($reference, 'Fiat withdrawal provider settlement', [
            ['account_id' => $reserve->id, 'amount' => $this->sub('0', (string) $intent->amount), 'asset' => $intent->currency],
            ['account_id' => $external->id, 'amount' => (string) $intent->recipient_receives, 'asset' => $intent->currency],
            ['account_id' => $treasury->id, 'amount' => (string) $intent->fee, 'asset' => $intent->currency],
        ], 'fiat_withdrawal_settlement', ['intent_uuid' => $intent->uuid]);

        $intent->update(['settlement_ledger_reference' => $reference, 'status' => 'successful', 'completed_at' => now()]);
        $intent->withdrawal?->update(['status' => 'completed', 'confirmed_at' => now()]);
    }

    private function reverseFailed(FiatWithdrawalIntent $intent, string $status): void
    {
        if ($intent->reversal_ledger_reference) {
            $intent->update(['status' => $status]);
            return;
        }

        $reference = 'FWD-REV-' . $intent->reference;
        $reserve = $this->ledger->getOrCreateAccount(null, 'fiat_withdrawal_reserve', $intent->currency);
        $funding = $this->ledger->getOrCreateAccount($intent->user_id, 'funding', $intent->currency);
        $this->ledger->postDoubleEntry($reference, 'Fiat withdrawal reversal', [
            ['account_id' => $reserve->id, 'amount' => $this->sub('0', (string) $intent->amount), 'asset' => $intent->currency],
            ['account_id' => $funding->id, 'amount' => (string) $intent->amount, 'asset' => $intent->currency, 'user_id' => $intent->user_id],
        ], 'fiat_withdrawal_reversal', ['intent_uuid' => $intent->uuid]);

        $intent->update(['reversal_ledger_reference' => $reference, 'status' => $status]);
        $intent->withdrawal?->update(['status' => $status === 'cancelled' ? 'cancelled' : 'failed']);
    }

    private function validateAmount(User $user, string $currency, string $amount): void
    {
        $this->seedFundingLedgerFromLegacyIfNeeded((int) $user->id, $currency);
        $minimum = $this->fmt((string) config('wallet.fiat_withdrawals.minimum', '10'));
        $maximum = $this->fmt((string) config('wallet.fiat_withdrawals.maximum', '10000'));
        if ($this->compare($amount, $minimum) < 0) {
            throw new RuntimeException("Minimum withdrawal is {$minimum} {$currency}.");
        }
        if ($this->compare($amount, $maximum) > 0) {
            throw new RuntimeException("Maximum withdrawal is {$maximum} {$currency}.");
        }
        if ($this->compare($this->fundingBalance((int) $user->id, $currency), $amount) < 0) {
            throw new RuntimeException('Insufficient Funding Wallet balance.');
        }
        if ($this->compare($this->meta($user, $currency)['limits']['remaining_daily'], $amount) < 0) {
            throw new RuntimeException('This amount exceeds your remaining daily withdrawal limit.');
        }
    }

    private function seedFundingLedgerFromLegacyIfNeeded(int $userId, string $currency): void
    {
        $currency = strtoupper($currency);
        $account = $this->ledger->getOrCreateAccount($userId, 'funding', $currency);
        if ($this->compare((string) $account->balance, '0') > 0) {
            return;
        }

        $legacy = WalletBalance::query()
            ->where('user_id', $userId)
            ->where('wallet_type', 'funding')
            ->where('asset', $currency)
            ->first();
        $legacyBalance = $this->fmt((string) ($legacy?->balance ?? '0'));
        if ($this->compare($legacyBalance, '0') <= 0) {
            return;
        }

        $reference = 'FWD-MIG-' . $userId . '-' . $currency;
        if (\App\Models\LedgerTransaction::query()->where('reference', $reference)->exists()) {
            return;
        }

        $migration = $this->ledger->getOrCreateAccount(null, 'legacy_funding_migration', $currency);
        $this->ledger->postDoubleEntry($reference, 'Seed funding ledger for fiat withdrawal from legacy funding wallet', [
            ['account_id' => $migration->id, 'amount' => $this->sub('0', $legacyBalance), 'asset' => $currency],
            ['account_id' => $account->id, 'amount' => $legacyBalance, 'asset' => $currency, 'user_id' => $userId],
        ], 'migration', ['source' => 'fiat_withdrawal_ledger_seed']);
    }

    private function fundingBalance(int $userId, string $currency): string
    {
        $account = Account::query()->where('user_id', $userId)->where('account_type', 'funding')->where('asset', strtoupper($currency))->first();
        return $account ? $this->fmt((string) $account->balance) : '0.00000000';
    }

    private function bankName(string $country, string $currency, string $bankCode): string
    {
        $bank = collect($this->banks($country, $currency))->firstWhere('code', $bankCode);
        return (string) ($bank['name'] ?? 'Selected Bank');
    }

    private function presentBeneficiary(FiatWithdrawalBeneficiary $beneficiary): array
    {
        return [
            'id' => $beneficiary->id,
            'country' => $beneficiary->country,
            'currency' => $beneficiary->currency,
            'provider' => $beneficiary->provider,
            'bank_name' => $beneficiary->bank_name,
            'account_name' => $beneficiary->account_name,
            'masked_account_number' => $beneficiary->masked_account_number,
            'is_default' => (bool) $beneficiary->is_default,
            'status' => $beneficiary->status,
        ];
    }

    private function normalizeProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful', 'success', 'completed', 'complete' => 'successful',
            'failed', 'failure', 'declined' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'reversed', 'refunded' => 'reversed',
            'review', 'under_review' => 'under_review',
            default => 'processing',
        };
    }

    private function fee(string $amount, string $currency): string
    {
        $flat = $this->fmt((string) config('wallet.fiat_withdrawals.flat_fee.' . strtoupper($currency), '1'));
        $percent = $this->fmt((string) config('wallet.fiat_withdrawals.percent_fee', '0.005'));
        $percentFee = function_exists('bcmul') ? bcmul($amount, $percent, self::SCALE) : number_format((float) $amount * (float) $percent, self::SCALE, '.', '');
        return $this->fmt($this->add($flat, $percentFee));
    }

    private function authorizeIntent(User $user, FiatWithdrawalIntent $intent): void
    {
        if ((int) $intent->user_id !== (int) $user->id) {
            throw new RuntimeException('Withdrawal not found.');
        }
    }

    private function maskAccount(string $accountNumber): string
    {
        $digits = preg_replace('/\D+/', '', $accountNumber);
        return str_repeat('*', max(strlen($digits) - 4, 2)) . substr($digits, -4);
    }

    private function fmt(string $value): string
    {
        return function_exists('bcadd') ? bcadd(trim($value), '0', self::SCALE) : number_format((float) $value, self::SCALE, '.', '');
    }

    private function add(string $left, string $right): string
    {
        return function_exists('bcadd') ? bcadd($left, $right, self::SCALE) : number_format((float) $left + (float) $right, self::SCALE, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        return function_exists('bcsub') ? bcsub($left, $right, self::SCALE) : number_format((float) $left - (float) $right, self::SCALE, '.', '');
    }

    private function max(string $left, string $right): string
    {
        return $this->compare($left, $right) >= 0 ? $left : $right;
    }

    private function compare(string $left, string $right): int
    {
        return function_exists('bccomp') ? bccomp($left, $right, self::SCALE) : ((float) $left <=> (float) $right);
    }
}
