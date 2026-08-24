<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinanceReconciliationBreak;
use App\Models\SecurityEmergencyControl;
use App\Models\SecurityRiskDecision;
use App\Models\SecurityRiskSignal;
use App\Models\User;
use Illuminate\Support\Str;

class SecurityRiskEngine
{
    public function __construct(private readonly SecuritySignalService $signals)
    {
    }

    public function evaluate(string $subjectType, ?int $subjectId, string $action, array $context = []): array
    {
        if ($this->emergencyActive($subjectType, $subjectId, $action)) {
            return $this->persist($subjectType, $subjectId, $action, 'EMERGENCY_LOCK', 100, ['EMERGENCY_CONTROL_ACTIVE'], [], 'CONTACT_SECURITY');
        }

        if (($context['engine_available'] ?? true) === false && $this->sensitiveAction($action)) {
            return $this->persist($subjectType, $subjectId, $action, 'BLOCK', 100, ['SECURITY_ENGINE_UNAVAILABLE_FAIL_CLOSED'], [], 'RETRY_AFTER_SECURITY_RECOVERY');
        }

        $active = $this->signals->activeFor($subjectType, $subjectId);
        $score = 0;
        $reasons = [];

        foreach ($active as $signal) {
            $score += $this->weight($signal);
            $reasons[] = $signal->signal_type;
        }

        if ($subjectType === 'USER' && $subjectId) {
            $user = User::query()->find($subjectId);
            if ($user && $user->withdrawal_locked_until && now()->lt($user->withdrawal_locked_until)) {
                $score += 35;
                $reasons[] = 'SECURITY_COOLDOWN_ACTIVE';
            }
        }

        if (FinanceReconciliationBreak::query()->whereIn('severity', ['HIGH', 'CRITICAL'])->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->exists()) {
            $score += $this->sensitiveAction($action) ? 30 : 10;
            $reasons[] = 'FINANCE_RECONCILIATION_CRITICAL';
        }

        [$decision, $required] = $this->decisionFor($score, $action);

        return $this->persist($subjectType, $subjectId, $action, $decision, min(100, $score), array_values(array_unique($reasons)), $active, $required);
    }

    public function assessWithdrawal(User $user, string $amount, array $context = []): array
    {
        $threshold = (string) config('security.transactions.large_withdrawal_threshold', '2000');
        if (bccomp($amount, $threshold, 8) >= 0) {
            $this->signals->record('WITHDRAWAL_SPIKE', 'WITHDRAWAL', 'USER', $user->id, 'HIGH', ['amount' => $amount], '0.9000', 3600);
        }
        if ($user->withdrawal_locked_until && now()->lt($user->withdrawal_locked_until)) {
            $this->signals->record('WITHDRAWAL_AFTER_SECURITY_CHANGE', 'ACCOUNT', 'USER', $user->id, 'HIGH', ['locked_until' => $user->withdrawal_locked_until], '0.9500', 3600);
        }

        return $this->evaluate('USER', $user->id, 'WITHDRAWAL', $context);
    }

    private function weight(SecurityRiskSignal $signal): int
    {
        return match (strtoupper($signal->severity)) {
            'CRITICAL' => 70,
            'HIGH' => 45,
            'MEDIUM' => 25,
            default => 10,
        };
    }

    private function decisionFor(int $score, string $action): array
    {
        if ($score >= 90) {
            return ['EMERGENCY_LOCK', 'CONTACT_SECURITY'];
        }
        if ($score >= 70) {
            return ['BLOCK', 'MANUAL_REVIEW'];
        }
        if ($score >= 50) {
            return [$this->sensitiveAction($action) ? 'TEMPORARY_HOLD' : 'MANUAL_REVIEW', 'SECURITY_REVIEW'];
        }
        if ($score >= 30) {
            return ['MFA_REQUIRED', 'STEP_UP_AUTHENTICATION'];
        }
        if ($score > 0) {
            return ['ALLOW_WITH_MONITORING', null];
        }

        return ['ALLOW', null];
    }

    private function persist(string $subjectType, ?int $subjectId, string $action, string $decision, int $score, array $reasons, array $signals, ?string $requiredAction): array
    {
        $level = match (true) {
            $score >= 70 => 'CRITICAL',
            $score >= 50 => 'HIGH',
            $score >= 30 => 'MEDIUM',
            $score > 0 => 'LOW',
            default => 'NONE',
        };

        $row = SecurityRiskDecision::query()->create([
            'decision_uuid' => (string) Str::uuid(),
            'subject_type' => strtoupper($subjectType),
            'subject_id' => $subjectId,
            'action' => strtoupper($action),
            'decision' => $decision,
            'risk_score' => $score,
            'risk_level' => $level,
            'reason_codes' => $reasons,
            'signals' => array_map(fn ($signal) => [
                'signal_uuid' => $signal->signal_uuid ?? null,
                'signal_type' => $signal->signal_type ?? null,
                'severity' => $signal->severity ?? null,
            ], $signals),
            'required_action' => $requiredAction,
            'decision_version' => 'phase18-v1',
            'expires_at' => in_array($decision, ['TEMPORARY_HOLD', 'MFA_REQUIRED'], true) ? now()->addMinutes(30) : null,
        ]);

        return $row->toArray();
    }

    private function emergencyActive(string $subjectType, ?int $subjectId, string $action): bool
    {
        return SecurityEmergencyControl::query()
            ->where('status', 'ACTIVE')
            ->where(function ($query) use ($subjectType, $subjectId): void {
                $query->where('scope_type', 'GLOBAL')
                    ->orWhere(fn ($scoped) => $scoped->where('scope_type', strtoupper($subjectType))->where('scope_reference', (string) $subjectId));
            })
            ->where(function ($query) use ($action): void {
                $query->where('control_type', 'GLOBAL_SECURITY_MODE')
                    ->orWhere('control_type', 'DISABLE_ACCOUNT_LOGIN')
                    ->orWhere('control_type', 'PAUSE_WITHDRAWALS')
                    ->orWhere('control_type', strtoupper($action));
            })
            ->exists();
    }

    private function sensitiveAction(string $action): bool
    {
        return in_array(strtoupper($action), ['WITHDRAWAL', 'API_KEY_CREATE', 'MFA_RESET', 'PASSWORD_RESET', 'ADMIN_PRIVILEGED_ACTION', 'SECURITY_SETTING_CHANGE'], true);
    }
}
