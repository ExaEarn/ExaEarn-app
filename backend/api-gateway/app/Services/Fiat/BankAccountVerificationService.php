<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankAccountVerificationService
{
    public function __construct(private readonly PaymentProviderRouter $router)
    {
    }

    public function verifyAndStore(User $user, array $payload): array
    {
        $provider = $this->router->provider($payload['provider'] ?? null, $payload['country'], $payload['currency'], 'account_verification');
        $accountNumber = preg_replace('/\D+/', '', (string) $payload['account_number']);
        $verified = $provider->verifyAccount((string) $payload['country'], (string) $payload['currency'], (string) $payload['bank_code'], $accountNumber);

        $identity = [
                'user_id' => $user->id,
                'country' => strtoupper((string) $payload['country']),
                'currency' => strtoupper((string) $payload['currency']),
                'bank_code' => (string) $payload['bank_code'],
                'account_number' => $accountNumber,
        ];
        $existing = DB::table('user_bank_accounts')->where($identity)->first();
        DB::table('user_bank_accounts')->updateOrInsert(
            $identity,
            [
                'bank_account_id' => $existing?->bank_account_id ?? (string) Str::uuid(),
                'provider' => $provider->key(),
                'bank_name' => (string) $payload['bank_name'],
                'masked_account_number' => $this->mask($accountNumber),
                'verified_account_name' => (string) $verified['account_name'],
                'verification_status' => $verified['verified'] ? 'VERIFIED' : 'UNVERIFIED',
                'verification_reference' => (string) ($verified['provider_reference'] ?? ''),
                'status' => 'ACTIVE',
                'metadata' => json_encode(['source' => 'bank_account_verification'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (array) DB::table('user_bank_accounts')
            ->where('user_id', $user->id)
            ->where('country', strtoupper((string) $payload['country']))
            ->where('currency', strtoupper((string) $payload['currency']))
            ->where('bank_code', (string) $payload['bank_code'])
            ->where('account_number', $accountNumber)
            ->first();
    }

    private function mask(string $accountNumber): string
    {
        return str_repeat('*', max(0, strlen($accountNumber) - 4)).substr($accountNumber, -4);
    }
}
