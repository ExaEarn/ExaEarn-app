<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;

class FiatTreasuryService
{
    public function allocate(string $currency, string $bucket, string $amount): array
    {
        $currency = strtoupper($currency);
        DB::table('fiat_treasury_balances')->updateOrInsert(
            ['currency' => $currency, 'bucket' => strtoupper($bucket)],
            [
                'available_amount' => FinancialDecimal::normalize($amount),
                'status' => 'ACTIVE',
                'metadata' => json_encode(['source' => 'phase10_treasury'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (array) DB::table('fiat_treasury_balances')->where('currency', $currency)->where('bucket', strtoupper($bucket))->first();
    }

    public function overview(?string $currency = null): array
    {
        $rows = DB::table('fiat_treasury_balances')
            ->when($currency, fn ($query) => $query->where('currency', strtoupper($currency)))
            ->orderBy('currency')
            ->orderBy('bucket')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ['balances' => $rows];
    }
}
