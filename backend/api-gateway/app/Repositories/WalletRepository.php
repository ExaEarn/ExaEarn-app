<?php

// declare(strict_types=1);

namespace App\Repositories;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;

class WalletRepository
{
    public function findOrCreate(int $userId, string $currency): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId, 'currency' => strtoupper($currency)],
            ['available_balance' => 0, 'locked_balance' => 0]
        );
    }

    public function lockWallet(int $userId, string $currency): Wallet
    {
        return Wallet::where('user_id', $userId)
            ->where('currency', strtoupper($currency))
            ->lockForUpdate()
            ->firstOr(function () use ($userId, $currency) {
                return $this->findOrCreate($userId, $currency);
            });
    }

    public function queryByUser(int $userId): Builder
    {
        return Wallet::query()->where('user_id', $userId);
    }
}
