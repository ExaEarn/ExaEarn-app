<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Reservation;
use App\Models\TreasuryAccount;
use App\Models\TreasuryBalance;
use RuntimeException;

class ConvertBackingService
{
    public function assertCapacity(string $destinationAsset, string $destinationAmount): array
    {
        $capacity = $this->capacityFor($destinationAsset);

        if (!$capacity['enforced']) {
            return $capacity;
        }

        if (FinancialDecimal::compare($capacity['available_conversion_capacity'], $destinationAmount, 18) < 0) {
            throw new RuntimeException('CONVERT_CAPACITY_UNAVAILABLE');
        }

        return $capacity;
    }

    public function capacityFor(string $asset): array
    {
        $asset = strtoupper($asset);
        $controlled = $this->controlledAssets($asset);
        $approvedReceivable = $this->policyDecimal($asset, 'approved_receivable', '0');
        $customerLiability = $this->customerLiability($asset);
        $withdrawalReserve = $this->policyDecimal($asset, 'withdrawal_reserve', '0');
        $otherReserved = $this->otherReserved($asset);

        $grossBacking = FinancialDecimal::add($controlled, $approvedReceivable, 18);
        $required = FinancialDecimal::add(
            FinancialDecimal::add($customerLiability, $withdrawalReserve, 18),
            $otherReserved,
            18
        );
        $available = FinancialDecimal::sub($grossBacking, $required, 18);
        if (FinancialDecimal::compare($available, '0', 18) < 0) {
            $available = '0.000000000000000000';
        }

        $status = $this->statusFor($asset, $available);

        return [
            'asset' => $asset,
            'enforced' => $this->isEnforced($asset),
            'controlled_assets' => FinancialDecimal::normalize($controlled),
            'approved_receivable' => FinancialDecimal::normalize($approvedReceivable),
            'customer_liability' => FinancialDecimal::normalize($customerLiability),
            'withdrawal_reserve' => FinancialDecimal::normalize($withdrawalReserve),
            'other_reserved' => FinancialDecimal::normalize($otherReserved),
            'available_conversion_capacity' => FinancialDecimal::normalize($available),
            'status' => $status,
            'policy_version' => (string) config('swap.treasury.policy_version', 'convert-treasury-v1'),
        ];
    }

    private function controlledAssets(string $asset): string
    {
        if (in_array($asset, array_map('strtoupper', (array) config('swap.supported_fiat', [])), true)) {
            $fiatTreasury = TreasuryAccount::query()
                ->where('currency', $asset)
                ->where('status', 'active')
                ->sum('available_balance');

            return FinancialDecimal::normalize((string) ($fiatTreasury ?: '0'));
        }

        $treasuryBalance = TreasuryBalance::query()->where('asset', $asset)->first();
        if ($treasuryBalance) {
            return FinancialDecimal::normalize((string) $treasuryBalance->balance);
        }

        $ledgerTreasury = Account::query()
            ->whereNull('user_id')
            ->whereIn('account_type', ['treasury', 'system_treasury', 'convert_inventory'])
            ->where('asset', $asset)
            ->sum('balance');

        return FinancialDecimal::normalize((string) ($ledgerTreasury ?: '0'));
    }

    private function customerLiability(string $asset): string
    {
        $sum = Account::query()
            ->whereNotNull('user_id')
            ->where('asset', $asset)
            ->sum('balance');

        return FinancialDecimal::normalize((string) ($sum ?: '0'));
    }

    private function otherReserved(string $asset): string
    {
        $sum = Reservation::query()
            ->where('asset', $asset)
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->whereIn('purpose', ['withdrawal', 'fiat_withdrawal', 'crypto_withdrawal', 'convert_capacity'])
            ->sum('remaining_amount');

        return FinancialDecimal::normalize((string) ($sum ?: '0'));
    }

    private function isEnforced(string $asset): bool
    {
        $assets = array_map('strtoupper', (array) config('swap.treasury.enforced_assets', []));

        return in_array($asset, $assets, true);
    }

    private function policyDecimal(string $asset, string $key, string $default): string
    {
        return FinancialDecimal::normalize((string) config("swap.treasury.asset_policies.{$asset}.{$key}", $default));
    }

    private function statusFor(string $asset, string $available): string
    {
        $critical = $this->policyDecimal($asset, 'critical_inventory', '0');
        $minimum = $this->policyDecimal($asset, 'minimum_inventory', '0');
        $rebalance = $this->policyDecimal($asset, 'rebalance_threshold', $minimum);

        if (FinancialDecimal::compare($available, '0', 18) <= 0) {
            return 'CONVERT_DISABLED';
        }

        if (FinancialDecimal::compare($available, $critical, 18) <= 0 && FinancialDecimal::compare($critical, '0', 18) > 0) {
            return 'CRITICAL';
        }

        if (FinancialDecimal::compare($available, $minimum, 18) <= 0 && FinancialDecimal::compare($minimum, '0', 18) > 0) {
            return 'LOW';
        }

        if (FinancialDecimal::compare($available, $rebalance, 18) <= 0 && FinancialDecimal::compare($rebalance, '0', 18) > 0) {
            return 'REBALANCE_REQUIRED';
        }

        return 'HEALTHY';
    }
}
