<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\CompliancePolicyChange;
use App\Models\CompliancePolicyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CompliancePolicyAdminService
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function submitRuleChange(Admin $admin, array $payload, ?\Illuminate\Http\Request $request = null): CompliancePolicyChange
    {
        $change = CompliancePolicyChange::query()->create([
            'change_uuid' => (string) Str::uuid(),
            'change_type' => 'RULE_CREATE',
            'rule_id' => null,
            'previous_value' => null,
            'new_value' => $this->normalizeRulePayload($payload),
            'status' => 'PENDING_APPROVAL',
            'submitted_by_admin_id' => $admin->id,
            'reason' => (string) $payload['reason'],
            'legal_reference' => $payload['legal_reference'] ?? null,
            'effective_at' => $payload['effective_at'] ?? now(),
            'expires_at' => $payload['expires_at'] ?? null,
        ]);

        $this->audit->log($admin, 'compliance.policy_change.submitted', ['change_uuid' => $change->change_uuid], $request);

        return $change->fresh();
    }

    public function approveChange(Admin $admin, CompliancePolicyChange $change, string $reason, ?\Illuminate\Http\Request $request = null): CompliancePolicyRule
    {
        if ((int) $change->submitted_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Compliance policy changes require maker-checker approval by a different admin.');
        }
        if ($change->status !== 'PENDING_APPROVAL') {
            throw new RuntimeException('Only pending compliance policy changes can be approved.');
        }

        return DB::transaction(function () use ($admin, $change, $reason, $request): CompliancePolicyRule {
            $payload = (array) $change->new_value;
            $rule = CompliancePolicyRule::query()->create(array_merge($payload, [
                'rule_uuid' => (string) Str::uuid(),
                'status' => 'ACTIVE',
                'submitted_by_admin_id' => $change->submitted_by_admin_id,
                'approved_by_admin_id' => $admin->id,
                'policy_version' => $payload['policy_version'] ?? (string) config('compliance.policy_version', 'phase16-v1'),
                'reason' => $change->reason,
                'legal_reference' => $change->legal_reference,
                'metadata' => array_merge($payload['metadata'] ?? [], [
                    'change_uuid' => $change->change_uuid,
                    'approval_reason' => $reason,
                ]),
            ]));

            $change->forceFill([
                'rule_id' => $rule->id,
                'status' => 'APPROVED',
                'approved_by_admin_id' => $admin->id,
                'reason' => $change->reason."\nApproval: ".$reason,
                'approved_at' => now(),
            ])->save();

            $this->audit->log($admin, 'compliance.policy_change.approved', [
                'change_uuid' => $change->change_uuid,
                'rule_uuid' => $rule->rule_uuid,
                'reason' => $reason,
            ], $request);

            return $rule->fresh();
        });
    }

    public function rejectChange(Admin $admin, CompliancePolicyChange $change, string $reason, ?\Illuminate\Http\Request $request = null): CompliancePolicyChange
    {
        if ($change->status !== 'PENDING_APPROVAL') {
            throw new RuntimeException('Only pending compliance policy changes can be rejected.');
        }

        $change->forceFill([
            'status' => 'REJECTED',
            'approved_by_admin_id' => $admin->id,
            'reason' => $change->reason."\nRejected: ".$reason,
            'approved_at' => now(),
        ])->save();

        $this->audit->log($admin, 'compliance.policy_change.rejected', [
            'change_uuid' => $change->change_uuid,
            'reason' => $reason,
        ], $request);

        return $change->fresh();
    }

    private function normalizeRulePayload(array $payload): array
    {
        return [
            'name' => (string) $payload['name'],
            'description' => $payload['description'] ?? null,
            'jurisdiction' => isset($payload['jurisdiction']) ? strtoupper((string) $payload['jurisdiction']) : null,
            'product_code' => isset($payload['product_code']) ? strtoupper((string) $payload['product_code']) : null,
            'account_type' => isset($payload['account_type']) ? strtoupper((string) $payload['account_type']) : null,
            'asset' => isset($payload['asset']) ? strtoupper((string) $payload['asset']) : null,
            'market_symbol' => isset($payload['market_symbol']) ? strtoupper((string) $payload['market_symbol']) : null,
            'network' => isset($payload['network']) ? strtoupper((string) $payload['network']) : null,
            'currency' => isset($payload['currency']) ? strtoupper((string) $payload['currency']) : null,
            'decision' => strtoupper((string) $payload['decision']),
            'reason_code' => strtoupper((string) $payload['reason_code']),
            'required_kyc_level' => (int) ($payload['required_kyc_level'] ?? 0),
            'required_kyb_tier' => $payload['required_kyb_tier'] ?? null,
            'max_amount' => $payload['max_amount'] ?? null,
            'max_leverage' => $payload['max_leverage'] ?? null,
            'precedence' => (int) ($payload['precedence'] ?? 100),
            'effective_at' => $payload['effective_at'] ?? now(),
            'expires_at' => $payload['expires_at'] ?? null,
            'limits' => $payload['limits'] ?? [],
            'metadata' => $payload['metadata'] ?? [],
        ];
    }
}
