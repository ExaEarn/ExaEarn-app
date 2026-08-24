<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiatCryptoQuoteService
{
    public function quote(string $fromAsset, string $toAsset, string $amount, string $side = 'buy'): array
    {
        $fromAsset = strtoupper($fromAsset);
        $toAsset = strtoupper($toAsset);
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            throw new RuntimeException('Quote amount must be greater than zero.');
        }

        $isFiat = DB::table('fiat_currencies')->whereIn('code', [$fromAsset, $toAsset])->exists();
        if (!$isFiat) {
            throw new RuntimeException('At least one side of a fiat conversion must be fiat.');
        }

        return [
            'from_asset' => $fromAsset,
            'to_asset' => $toAsset,
            'amount' => FinancialDecimal::normalize($amount),
            'side' => strtolower($side),
            'source' => 'PHASE4_CONVERT_ENGINE_REQUIRED',
            'status' => 'QUOTE_REQUIRES_PHASE4_EXECUTION_PATH',
        ];
    }
}
