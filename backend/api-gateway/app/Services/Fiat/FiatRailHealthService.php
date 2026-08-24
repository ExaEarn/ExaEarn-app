<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;

class FiatRailHealthService
{
    public function rails(): array
    {
        return DB::table('fiat_currencies')->orderBy('code')->get()->map(function ($currency): array {
            return [
                'currency' => $currency->code,
                'deposits' => $currency->deposit_enabled ? ($currency->status === 'LIVE' ? 'OPERATIONAL' : 'DEGRADED') : 'PAUSED',
                'withdrawals' => $currency->withdrawal_enabled ? ($currency->status === 'LIVE' ? 'OPERATIONAL' : 'DEGRADED') : 'PAUSED',
                'convert' => $currency->convert_enabled ? 'DEGRADED' : 'PAUSED',
            ];
        })->all();
    }
}
