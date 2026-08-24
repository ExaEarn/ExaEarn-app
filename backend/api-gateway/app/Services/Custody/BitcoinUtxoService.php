<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BitcoinUtxoService
{
    public function selectAndReserve(string $amount, string $reservationReference): array
    {
        return DB::transaction(function () use ($amount, $reservationReference): array {
            $selected = [];
            $total = '0';
            $utxos = DB::table('bitcoin_utxos')->where('spend_status', 'UNSPENT')->orderBy('amount')->lockForUpdate()->get();
            foreach ($utxos as $utxo) {
                $selected[] = (array) $utxo;
                $total = FinancialDecimal::add($total, (string) $utxo->amount);
                DB::table('bitcoin_utxos')->where('id', $utxo->id)->update([
                    'spend_status' => 'RESERVED',
                    'reservation_reference' => $reservationReference,
                    'updated_at' => now(),
                ]);
                if (FinancialDecimal::compare($total, $amount) >= 0) {
                    return ['utxos' => $selected, 'total' => $total, 'change' => FinancialDecimal::sub($total, $amount)];
                }
            }

            throw new RuntimeException('Insufficient Bitcoin UTXO liquidity.');
        });
    }
}
