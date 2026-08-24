<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProviderSettlementService
{
    public function record(array $payload): array
    {
        $gross = FinancialDecimal::normalize((string) $payload['gross_amount']);
        $fee = FinancialDecimal::normalize((string) ($payload['fee_amount'] ?? '0'));
        $net = FinancialDecimal::sub($gross, $fee);
        DB::table('provider_settlements')->updateOrInsert(
            ['provider' => (string) $payload['provider'], 'provider_settlement_id' => (string) $payload['provider_settlement_id']],
            [
                'settlement_uuid' => (string) Str::uuid(),
                'currency' => strtoupper((string) $payload['currency']),
                'gross_amount' => $gross,
                'fee_amount' => $fee,
                'net_amount' => $net,
                'destination_bank' => $payload['destination_bank'] ?? null,
                'settlement_date' => $payload['settlement_date'] ?? now()->toDateString(),
                'status' => $payload['status'] ?? 'PENDING',
                'metadata' => json_encode($payload['metadata'] ?? [], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (array) DB::table('provider_settlements')
            ->where('provider', (string) $payload['provider'])
            ->where('provider_settlement_id', (string) $payload['provider_settlement_id'])
            ->first();
    }
}
