<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VirtualAccountService
{
    public function __construct(private readonly PaymentProviderRouter $router, private readonly FiatCurrencyRegistry $currencies)
    {
    }

    public function getOrCreate(User $user, string $currency, string $country = 'NG', ?string $provider = null): array
    {
        $currency = strtoupper($currency);
        $country = strtoupper($country);
        $currencyConfig = $this->currencies->currency($currency);
        if (!(bool) $currencyConfig['deposit_enabled']) {
            throw new RuntimeException('Fiat deposits are not enabled for this currency.');
        }

        return DB::transaction(function () use ($country, $currency, $provider, $user): array {
            $existing = DB::table('phase10_virtual_accounts')
                ->where('user_id', $user->id)
                ->where('currency', $currency)
                ->where('country', $country)
                ->where('status', 'ACTIVE')
                ->first();
            if ($existing) {
                return (array) $existing;
            }

            $paymentProvider = $this->router->provider($provider, $country, $currency, 'virtual_accounts');
            if (!($paymentProvider->capabilities()['virtual_accounts'] ?? false)) {
                throw new RuntimeException('Selected provider does not support virtual accounts.');
            }
            $reference = 'VA-'.strtoupper(Str::random(16));
            $created = $paymentProvider->createVirtualAccount((int) $user->id, $currency, $country, $reference);

            $pk = DB::table('phase10_virtual_accounts')->insertGetId([
                'virtual_account_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'currency' => $currency,
                'country' => $country,
                'provider' => $paymentProvider->key(),
                'provider_account_id' => $created['provider_account_id'] ?? null,
                'account_number' => (string) $created['account_number'],
                'account_name' => (string) $created['account_name'],
                'bank_code' => $created['bank_code'] ?? null,
                'bank_name' => $created['bank_name'] ?? null,
                'reference' => $reference,
                'status' => (string) ($created['status'] ?? 'ACTIVE'),
                'metadata' => json_encode(['provider_state' => $paymentProvider->state()], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (array) DB::table('phase10_virtual_accounts')->where('id', $pk)->first();
        });
    }
}
