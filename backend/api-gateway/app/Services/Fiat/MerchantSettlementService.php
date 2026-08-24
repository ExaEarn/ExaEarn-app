<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantSettlementService
{
    public function create(int $merchantId, string $currency): array
    {
        $gross = (string) DB::table('exaearn_pay_intents')
            ->where('merchant_id', $merchantId)
            ->where('currency', strtoupper($currency))
            ->where('status', 'CAPTURED')
            ->sum('amount');
        $fees = (string) DB::table('exaearn_pay_intents')
            ->where('merchant_id', $merchantId)
            ->where('currency', strtoupper($currency))
            ->where('status', 'CAPTURED')
            ->sum('fee_amount');
        $pk = DB::table('merchant_settlements')->insertGetId([
            'settlement_id' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'currency' => strtoupper($currency),
            'gross_amount' => $gross,
            'fee_amount' => $fees,
            'net_amount' => FinancialDecimal::sub($gross, $fees),
            'status' => 'PENDING',
            'metadata' => json_encode(['source' => 'phase10_merchant_settlement'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('merchant_settlements')->where('id', $pk)->first();
    }
}
