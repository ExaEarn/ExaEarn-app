<?php

namespace App\Services;

use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VirtualAccountService
{
    public function create(User $user, string $currency = 'NGN')
    {
        $existing = VirtualAccount::where('user_id', $user->id)->where('status', 'active')->first();
        if ($existing) {
            return $existing;
        }

        return $this->createViaNomba($user, strtoupper($currency));
    }

    private function createViaNomba(User $user, string $currency)
    {
        if (app()->environment(['local', 'testing'])) {
            return VirtualAccount::create([
                'user_id' => $user->id,
                'provider' => 'nomba',
                'account_name' => $user->name,
                'account_number' => '1234567890',
                'bank_name' => 'Nomba Test Bank',
                'reference' => 'REF-' . $user->id,
                'status' => 'active',
                'metadata' => ['currency' => $currency],
            ]);
        }

        $token = (string) config('services.nomba.token');
        $baseUrl = rtrim((string) config('services.nomba.url', 'https://api.nomba.com'), '/');
        $accountId = (string) config('services.nomba.account_id');

        if ($token === '' || $baseUrl === '' || $accountId === '') {
            Log::warning('Nomba virtual account creation skipped because required configuration is missing.');
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['accountId' => $accountId])
                ->post($baseUrl . '/v1/accounts/virtual', [
                    'accountRef' => 'REF-' . $user->id,
                    'accountName' => $user->name,
                    'currency' => $currency,
                ]);

            if ($response->successful()) {
                $data = (array) data_get($response->json(), 'data', []);
                return VirtualAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'nomba',
                    'account_name' => (string) ($data['accountName'] ?? $data['bankAccountName'] ?? $user->name),
                    'account_number' => (string) ($data['bankAccountNumber'] ?? $data['accountNumber'] ?? ''),
                    'bank_name' => (string) ($data['bankName'] ?? 'Nomba'),
                    'reference' => (string) ($data['accountRef'] ?? ('REF-' . $user->id)),
                    'status' => 'active',
                    'metadata' => $response->json(),
                ]);
            }

            Log::error('Nomba API Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Nomba API Exception: ' . $e->getMessage());
            return null;
        }
    }
}
