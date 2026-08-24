<?php

declare(strict_types=1);

namespace App\Services\Custody;

use Illuminate\Support\Facades\DB;

class BlockchainNonceService
{
    public function reserveNext(string $network, string $address): int
    {
        return DB::transaction(function () use ($address, $network): int {
            $row = DB::table('blockchain_nonce_states')
                ->where('network', strtolower($network))
                ->where('address', $address)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::table('blockchain_nonce_states')->insert([
                    'network' => strtolower($network),
                    'address' => $address,
                    'next_nonce' => 1,
                    'status' => 'READY',
                    'last_synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 0;
            }

            $nonce = (int) $row->next_nonce;
            DB::table('blockchain_nonce_states')->where('id', $row->id)->update([
                'next_nonce' => $nonce + 1,
                'updated_at' => now(),
            ]);

            return $nonce;
        });
    }
}
