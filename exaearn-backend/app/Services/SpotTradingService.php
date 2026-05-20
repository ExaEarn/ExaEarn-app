<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class SpotTradingService
{
    public function lockFunds(int $userId, string $asset, string $amount, string $orderId)
    {
        return DB::transaction(function () use ($userId, $asset, $amount, $orderId) {
            $balance = Balance::where('user_id', $userId)->where('asset', $asset)->lockForUpdate()->firstOrFail();

            if ($balance->spot_available < $amount) {
                throw new Exception("Insufficient funds");
            }

            $beforeAvailable = $balance->spot_available;
            $beforeLocked = $balance->spot_locked;

            $balance->spot_available -= $amount;
            $balance->spot_locked += $amount;
            $balance->save();

            LedgerEntry::create([
                'user_id' => $userId,
                'wallet_type' => 'spot',
                'asset' => $asset,
                'amount' => -$amount,
                'type' => 'lock',
                'reference_id' => $orderId,
                'balance_before' => $beforeAvailable,
                'balance_after' => $balance->spot_available,
            ]);

            return $balance;
        });
    }

    public function unlockFunds(int $userId, string $asset, string $amount, string $orderId)
    {
        return DB::transaction(function () use ($userId, $asset, $amount, $orderId) {
            $balance = Balance::where('user_id', $userId)->where('asset', $asset)->lockForUpdate()->firstOrFail();

            if ($balance->spot_locked < $amount) {
                throw new Exception("Insufficient locked funds");
            }

            $beforeAvailable = $balance->spot_available;
            $beforeLocked = $balance->spot_locked;

            $balance->spot_locked -= $amount;
            $balance->spot_available += $amount;
            $balance->save();

            LedgerEntry::create([
                'user_id' => $userId,
                'wallet_type' => 'spot',
                'asset' => $asset,
                'amount' => $amount,
                'type' => 'unlock',
                'reference_id' => $orderId,
                'balance_before' => $beforeAvailable,
                'balance_after' => $balance->spot_available,
            ]);

            return $balance;
        });
    }
}
