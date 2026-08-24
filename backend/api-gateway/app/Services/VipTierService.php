<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\VipTierDefinition;
use App\Models\VipTierHistory;
use RuntimeException;

class VipTierService
{
    private const ORDER = ['STANDARD' => 0, 'VIP_1' => 1, 'VIP_2' => 2, 'VIP_3' => 3, 'VIP_4' => 4, 'VIP_5' => 5];

    public function calculate(array $inputs): string
    {
        $tier = 'STANDARD';
        foreach (VipTierDefinition::query()->where('active', true)->get() as $definition) {
            $passes = FinancialDecimal::compare((string) ($inputs['spot_volume_30d'] ?? '0'), (string) $definition->min_30d_spot_volume) >= 0
                || FinancialDecimal::compare((string) ($inputs['futures_volume_30d'] ?? '0'), (string) $definition->min_30d_futures_volume) >= 0
                || FinancialDecimal::compare((string) ($inputs['average_balance'] ?? '0'), (string) $definition->min_average_balance) >= 0;
            if ($passes && (self::ORDER[$definition->tier] ?? -1) > (self::ORDER[$tier] ?? -1)) {
                $tier = (string) $definition->tier;
            }
        }

        return $tier;
    }

    public function effectiveTier(InstitutionalAccount $institution, array $inputs = [], ?string $manualOverride = null, ?string $contractualTier = null): string
    {
        if ($institution->compliance_status !== 'APPROVED' || ! in_array($institution->status, ['ACTIVE', 'APPROVED'], true)) {
            return 'STANDARD';
        }

        $automatic = $this->calculate($inputs);
        $effective = $automatic;
        foreach ([$manualOverride, $contractualTier] as $candidate) {
            if ($candidate && (self::ORDER[$candidate] ?? -1) > (self::ORDER[$effective] ?? -1)) {
                $effective = $candidate;
            }
        }

        return $effective;
    }

    public function updateTier(InstitutionalAccount $institution, array $inputs, ?Admin $admin = null, ?string $manualOverride = null, ?string $contractualTier = null, string $reason = 'VIP recalculation'): VipTierHistory
    {
        if ($manualOverride && ! isset(self::ORDER[$manualOverride])) {
            throw new RuntimeException('Unsupported manual VIP tier.');
        }
        if ($contractualTier && ! isset(self::ORDER[$contractualTier])) {
            throw new RuntimeException('Unsupported contractual VIP tier.');
        }

        $automatic = $this->calculate($inputs);
        $effective = $this->effectiveTier($institution, $inputs, $manualOverride, $contractualTier);
        $previous = (string) $institution->vip_tier;
        $institution->forceFill(['vip_tier' => $effective])->save();

        return VipTierHistory::query()->create([
            'institution_id' => $institution->id,
            'previous_tier' => $previous,
            'automatic_tier' => $automatic,
            'manual_override_tier' => $manualOverride,
            'contractual_tier' => $contractualTier,
            'effective_tier' => $effective,
            'reason' => $reason,
            'changed_by_admin_id' => $admin?->id,
            'inputs' => $inputs,
        ]);
    }
}
