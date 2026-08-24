<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentDisputeService
{
    public function open(array $payload): array
    {
        $pk = DB::table('payment_disputes')->insertGetId([
            'dispute_id' => (string) Str::uuid(),
            'user_id' => $payload['user_id'] ?? null,
            'fiat_deposit_id' => $payload['fiat_deposit_id'] ?? null,
            'provider' => (string) $payload['provider'],
            'provider_reference' => (string) $payload['provider_reference'],
            'currency' => strtoupper((string) $payload['currency']),
            'amount' => (string) $payload['amount'],
            'status' => 'OPEN',
            'metadata' => json_encode($payload['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('payment_disputes')->where('id', $pk)->first();
    }
}
