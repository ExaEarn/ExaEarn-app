<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;

class BankDirectoryService
{
    public function __construct(private readonly PaymentProviderRouter $router)
    {
    }

    public function list(string $country, string $currency, ?string $provider = null): array
    {
        $paymentProvider = $this->router->provider($provider, $country, $currency, 'banks');
        $banks = $paymentProvider->listBanks($country, $currency);
        foreach ($banks as $bank) {
            DB::table('bank_directory_entries')->updateOrInsert(
                [
                    'provider' => $paymentProvider->key(),
                    'country' => strtoupper($country),
                    'currency' => strtoupper($currency),
                    'bank_code' => (string) $bank['bank_code'],
                ],
                [
                    'bank_name' => (string) $bank['bank_name'],
                    'transfer_supported' => (bool) ($bank['transfer_supported'] ?? false),
                    'account_verification_supported' => (bool) ($bank['account_verification_supported'] ?? false),
                    'status' => 'ACTIVE',
                    'metadata' => json_encode($bank, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $banks;
    }
}
