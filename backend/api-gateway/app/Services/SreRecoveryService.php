<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\SreRecoveryAction;
use Illuminate\Support\Str;
use RuntimeException;

class SreRecoveryService
{
    public function __construct(
        private readonly FinanceReadinessService $finance,
        private readonly SecurityReadinessService $security,
    ) {
    }

    public function request(Admin $admin, string $type, string $scope, ?string $reference, string $reason): SreRecoveryAction
    {
        return SreRecoveryAction::query()->create([
            'action_uuid' => (string) Str::uuid(),
            'action_type' => strtoupper($type),
            'scope' => strtoupper($scope),
            'scope_reference' => $reference,
            'status' => 'REQUESTED',
            'requested_by_type' => Admin::class,
            'requested_by_id' => $admin->id,
            'reason' => $reason,
            'prechecks' => $this->prechecks(),
        ]);
    }

    public function approve(Admin $admin, SreRecoveryAction $action): SreRecoveryAction
    {
        if ((int) $action->requested_by_id === (int) $admin->id && $action->requested_by_type === Admin::class) {
            throw new RuntimeException('Recovery approval requires segregation of duties.');
        }
        if ($action->status !== 'REQUESTED') {
            throw new RuntimeException('Only requested recovery actions can be approved.');
        }

        $action->forceFill([
            'status' => 'APPROVED',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ])->save();

        return $action->fresh();
    }

    public function execute(SreRecoveryAction $action): SreRecoveryAction
    {
        if ($action->status !== 'APPROVED') {
            throw new RuntimeException('Recovery action must be approved before execution.');
        }

        $prechecks = $this->prechecks();
        $blocked = ($prechecks['finance']['status'] ?? 'READY') !== 'READY' || ($prechecks['security']['status'] ?? 'READY') !== 'READY';
        $action->forceFill([
            'status' => $blocked ? 'BLOCKED' : 'EXECUTED_SAFE_MODE',
            'prechecks' => $prechecks,
            'result' => [
                'resume_mode' => $blocked ? 'BLOCKED' : 'SAFE_MODE',
                'normal_requires' => ['finance_reconciliation', 'security_clearance', 'business_readiness'],
            ],
            'executed_at' => now(),
        ])->save();

        return $action->fresh();
    }

    public function prechecks(): array
    {
        return [
            'finance' => $this->finance->evaluate(),
            'security' => $this->security->evaluate(),
            'compliance' => ['status' => 'READY', 'mode' => 'FAIL_CLOSED_ON_CACHE_LOSS'],
        ];
    }
}
