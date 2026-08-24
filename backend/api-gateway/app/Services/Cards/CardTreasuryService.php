<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\CardProviderBalance;
use App\Services\FinancialDecimal;
use Illuminate\Support\Str;

class CardTreasuryService
{
    public function __construct(private readonly CardOperationsAlertService $alerts)
    {
    }

    public function upsertProviderBalance(string $provider, string $currency, string $available, string $requiredMinimum = '0', string $target = '0'): CardProviderBalance
    {
        $status = FinancialDecimal::compare($available, $requiredMinimum) < 0 ? 'REBALANCE_REQUIRED' : 'HEALTHY';

        $balance = CardProviderBalance::query()->updateOrCreate([
            'provider' => strtolower($provider),
            'currency' => strtoupper($currency),
        ], [
            'balance_uuid' => (string) Str::uuid(),
            'available' => FinancialDecimal::normalize($available),
            'required_minimum' => FinancialDecimal::normalize($requiredMinimum),
            'target' => FinancialDecimal::normalize($target),
            'status' => $status,
            'checked_at' => now(),
            'metadata' => ['source' => 'manual_or_provider_snapshot'],
        ]);

        if ($status === 'REBALANCE_REQUIRED') {
            $this->alerts->lowProviderBalance(strtolower($provider), strtoupper($currency), (string) $balance->available, (string) $balance->required_minimum, (string) $balance->target);
        }

        return $balance;
    }

    public function overview(): array
    {
        return [
            'provider_balances' => CardProviderBalance::query()->orderBy('provider')->orderBy('currency')->get()->toArray(),
            'rebalance_required_count' => CardProviderBalance::query()->where('status', 'REBALANCE_REQUIRED')->count(),
        ];
    }
}
