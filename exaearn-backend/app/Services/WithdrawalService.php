<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\LedgerEntry;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Exception;

class WithdrawalService
{
    public function request(int $userId, string $asset, string $amount, string $address)
    {
        return DB::transaction(function () use ($userId, $asset, $amount, $address) {
            $balance = Balance::where('user_id', $userId)->where('asset', $asset)->lockForUpdate()->firstOrFail();

            if ($balance->funding_available < $amount) {
                throw new Exception("Insufficient funds in funding wallet");
            }

            $before = $balance->funding_available;
            $balance->funding_available -= $amount;
            $balance->save();

            LedgerEntry::create([
                'user_id' => $userId,
                'wallet_type' => 'funding',
                'asset' => $asset,
                'amount' => -$amount,
                'type' => 'withdrawal',
                'reference_id' => 'temp_id', // to be updated
                'balance_before' => $before,
                'balance_after' => $balance->funding_available,
                'status' => 'pending'
            ]);

            $withdrawal = Withdrawal::create([
                'user_id' => $userId,
                'asset' => $asset,
                'amount' => $amount,
                'address' => $address,
                'status' => 'pending'
            ]);

            return $withdrawal;
        });
    }
}
