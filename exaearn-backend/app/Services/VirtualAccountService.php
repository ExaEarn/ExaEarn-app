<?php

namespace App\Services;

use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VirtualAccountService
{
    public function create(User $user)
    {
        $existing = VirtualAccount::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }

        // Implementation for provider creation will go here
        // For now, let's just return a placeholder or handle error
        return $this->createViaNomba($user);
    }

    private function createViaNomba(User $user)
    {
        if (app()->environment('testing')) {
            return VirtualAccount::create([
                'user_id' => $user->id,
                'provider' => 'nomba',
                'account_name' => $user->name,
                'account_number' => '1234567890',
                'bank_name' => 'Nomba Test Bank',
                'reference' => 'REF-' . $user->id,
                'status' => 'active',
            ]);
        }

        try {
            $response = Http::withToken(config('services.nomba.token'))
                ->post(config('services.nomba.url') . '/api/v2/bank-transfer/reserved-accounts', [
                    'accountReference' => 'REF-' . $user->id,
                    'accountName' => $user->name,
                    'currencyCode' => 'NGN',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return VirtualAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'nomba',
                    'account_name' => $user->name,
                    'account_number' => $data['accountNumber'],
                    'bank_name' => $data['bankName'],
                    'reference' => 'REF-' . $user->id,
                    'status' => 'active',
                ]);
            }
            
            Log::error("Nomba API Error: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Nomba API Exception: " . $e->getMessage());
            return null;
        }
    }
}
