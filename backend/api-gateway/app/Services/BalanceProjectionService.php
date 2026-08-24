<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Reservation;
use Illuminate\Support\Collection;

class BalanceProjectionService
{
    public function accountProjection(Account $account): array
    {
        $total = FinancialDecimal::normalize((string) $account->balance);
        $reserved = $this->reservedForAccount((int) $account->id);
        $available = FinancialDecimal::sub($total, $reserved);

        return [
            'account_id' => $account->id,
            'owner_type' => $account->owner_type ?? ($account->user_id ? 'user' : 'system'),
            'owner_id' => $account->owner_id ?? $account->user_id,
            'user_id' => $account->user_id,
            'account_type' => $account->account_type,
            'asset' => strtoupper((string) $account->asset),
            'total' => $total,
            'available' => $available,
            'reserved' => $reserved,
            'locked' => $reserved,
            'status' => $account->status ?? 'active',
        ];
    }

    public function userBalances(int $userId, ?string $accountType = null): Collection
    {
        return Account::query()
            ->where('user_id', $userId)
            ->when($accountType, fn ($query) => $query->where('account_type', $accountType))
            ->orderBy('account_type')
            ->orderBy('asset')
            ->get()
            ->map(fn (Account $account): array => $this->accountProjection($account));
    }

    public function byUserAccountAndAsset(int $userId, string $accountType, string $asset): array
    {
        $account = Account::query()
            ->where('user_id', $userId)
            ->where('account_type', $accountType)
            ->where('asset', strtoupper($asset))
            ->first();

        if (!$account) {
            return [
                'account_id' => null,
                'user_id' => $userId,
                'account_type' => $accountType,
                'asset' => strtoupper($asset),
                'total' => '0.000000000000000000',
                'available' => '0.000000000000000000',
                'reserved' => '0.000000000000000000',
                'locked' => '0.000000000000000000',
                'status' => 'missing',
            ];
        }

        return $this->accountProjection($account);
    }

    public function reservedForAccount(int $accountId): string
    {
        $sum = Reservation::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->sum('remaining_amount');

        return FinancialDecimal::normalize((string) ($sum ?: '0'));
    }
}
