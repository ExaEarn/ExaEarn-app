<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferRecipientService
{
    public function getOrCreate(int $userBankAccountId, string $provider): array
    {
        return DB::transaction(function () use ($provider, $userBankAccountId): array {
            $existing = DB::table('provider_transfer_recipients')
                ->where('provider', $provider)
                ->where('user_bank_account_id', $userBankAccountId)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $bank = DB::table('user_bank_accounts')->where('id', $userBankAccountId)->firstOrFail();
            $pk = DB::table('provider_transfer_recipients')->insertGetId([
                'recipient_id' => (string) Str::uuid(),
                'user_bank_account_id' => $userBankAccountId,
                'provider' => $provider,
                'provider_recipient_id' => 'recipient-'.hash('sha256', $provider.'|'.$bank->bank_code.'|'.$bank->account_number),
                'status' => 'ACTIVE',
                'metadata' => json_encode(['source' => 'phase10_fiat'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('provider_transfer_recipients')->where('id', $pk)->first();
        });
    }
}
