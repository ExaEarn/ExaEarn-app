<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;
use Illuminate\Support\Str;
use RuntimeException;

class MarginAccountService
{
    public function getOrCreateCrossAccount(int $userId): MarginAccount
    {
        return MarginAccount::query()->firstOrCreate([
            'user_id' => $userId,
            'mode' => MarginAccount::MODE_CROSS,
            'market_symbol' => null,
        ], [
            'account_uuid' => (string) Str::uuid(),
            'status' => MarginAccount::STATUS_ACTIVE,
            'metadata' => ['source' => 'phase6_margin'],
        ]);
    }

    public function getOrCreateIsolatedAccount(int $userId, string $marketSymbol): MarginAccount
    {
        $symbol = $this->normalizeSymbol($marketSymbol);

        return MarginAccount::query()->firstOrCreate([
            'user_id' => $userId,
            'mode' => MarginAccount::MODE_ISOLATED,
            'market_symbol' => $symbol,
        ], [
            'account_uuid' => (string) Str::uuid(),
            'status' => MarginAccount::STATUS_ACTIVE,
            'metadata' => ['source' => 'phase6_margin'],
        ]);
    }

    public function ledgerAccountType(MarginAccount $account): string
    {
        if ($account->mode === MarginAccount::MODE_CROSS) {
            return 'margin_cross';
        }

        if (!$account->market_symbol) {
            throw new RuntimeException('Isolated margin account requires a market symbol.');
        }

        return 'margin_isolated_' . strtolower(str_replace('/', '_', $account->market_symbol));
    }

    public function assertActive(MarginAccount $account): void
    {
        if ($account->status !== MarginAccount::STATUS_ACTIVE) {
            throw new RuntimeException('Margin account is not active.');
        }
    }

    public function normalizeSymbol(string $symbol): string
    {
        $symbol = strtoupper(trim(str_replace('-', '/', $symbol)));
        if (!str_contains($symbol, '/') && str_ends_with($symbol, 'USDT')) {
            $symbol = substr($symbol, 0, -4) . '/USDT';
        }

        return $symbol;
    }
}
