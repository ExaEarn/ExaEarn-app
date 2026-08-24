<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;

class NetworkFeeManagementService
{
    public function status(?string $network = null): array
    {
        $query = DB::table('custody_network_fee_reserves');
        if ($network) {
            $query->where('network', strtolower($network));
        }

        return $query->orderBy('network')->get()->map(fn ($row) => (array) $row)->all();
    }

    public function ensureReserve(string $network, string $asset, string $minimum): array
    {
        DB::table('custody_network_fee_reserves')->updateOrInsert(
            ['network' => strtolower($network), 'asset' => strtoupper($asset)],
            [
                'minimum_amount' => FinancialDecimal::normalize($minimum),
                'status' => 'LOW',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (array) DB::table('custody_network_fee_reserves')->where('network', strtolower($network))->where('asset', strtoupper($asset))->first();
    }
}
