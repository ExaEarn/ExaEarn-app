<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiAuditLog;
use App\Models\ExaAiCapitalAllocation;
use App\Models\ExaAiMarketEligibility;
use App\Models\ExaAiPlan;
use App\Models\ExaAiSession;
use App\Models\ExaAiStrategyDefinition;
use App\Models\ExaAiSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExaAiEntitlementService
{
    private const SCALE = 8;

    public function __construct(
        private readonly CompliancePolicyService $compliance,
        private readonly SecurityRiskEngine $security,
    ) {
    }

    public function effectiveFor(User $user, array $context = []): array
    {
        $subscription = ExaAiSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due', 'expired'])
            ->orderByDesc('id')
            ->first();

        $plan = $subscription?->plan;
        $product = strtolower((string) ($context['product'] ?? 'spot'));
        $strategyCode = strtolower((string) ($context['strategy_code'] ?? ''));
        $symbol = strtoupper(str_replace('/', '', (string) ($context['symbol'] ?? '')));
        $requestedCapital = $this->fmt((string) ($context['requested_capital'] ?? '0'));
        $requestedLeverage = (int) ($context['requested_leverage'] ?? 1);

        $entitlements = $plan ? $this->planEntitlements($plan) : $this->emptyEntitlements();
        $reasons = [];
        $mode = 'NORMAL';
        $allowed = true;

        if (! $subscription || ! $plan) {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'SUBSCRIPTION_REQUIRED';
        } elseif ($subscription->status !== 'active') {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'SUBSCRIPTION_NOT_ACTIVE';
        } elseif ($subscription->ends_at && $subscription->ends_at->isPast()) {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'SUBSCRIPTION_EXPIRED';
        }

        $accountStatus = strtoupper((string) ($user->account_status ?? 'FULLY_ACTIVE'));
        if (! in_array($accountStatus, ['ACTIVE', 'FULLY_ACTIVE'], true)) {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'ACCOUNT_NOT_ACTIVE';
        }

        if ($product === 'spot' && ! (bool) $entitlements['spot_enabled']) {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'PLAN_SPOT_DISABLED';
        }

        if ($product === 'futures' && ! (bool) $entitlements['futures_enabled']) {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'PLAN_FUTURES_DISABLED';
        }

        if ($strategyCode !== '' && ! in_array($strategyCode, $entitlements['allowed_strategies'], true)) {
            $allowed = false;
            $mode = 'NO_NEW_RISK';
            $reasons[] = 'STRATEGY_NOT_ENTITLED';
        }

        if ($this->compare($requestedCapital, '0') > 0 && $this->compare($requestedCapital, $entitlements['maximum_ai_capital']) > 0) {
            $allowed = false;
            $mode = 'USER_ACTION_REQUIRED';
            $reasons[] = 'CAPITAL_LIMIT_EXCEEDED';
        }

        if ($requestedLeverage > (int) $entitlements['maximum_leverage']) {
            $allowed = false;
            $mode = 'USER_ACTION_REQUIRED';
            $reasons[] = 'LEVERAGE_LIMIT_EXCEEDED';
        }

        $openPositions = ExaAiSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->sum('max_open_positions');
        if ((int) $entitlements['maximum_positions'] > 0 && (int) $openPositions > (int) $entitlements['maximum_positions']) {
            $allowed = false;
            $mode = 'USER_ACTION_REQUIRED';
            $reasons[] = 'POSITION_LIMIT_EXCEEDED_AFTER_PLAN_CHANGE';
        }

        $market = null;
        if ($symbol !== '') {
            $market = ExaAiMarketEligibility::query()
                ->where('symbol', $symbol)
                ->where('product', $product)
                ->first();
            if (! $market || $market->status !== 'enabled') {
                $allowed = false;
                $mode = 'NO_NEW_RISK';
                $reasons[] = 'MARKET_NOT_ELIGIBLE';
            }
        }

        $compliance = $this->compliance->decide($user, $product === 'futures' ? 'EXAAI_FUTURES' : 'EXAAI_SPOT', [
            'action' => $context['action'] ?? 'NEW_RISK',
            'symbol' => $symbol,
            'requested_leverage' => $requestedLeverage,
            'log' => $context['log_compliance'] ?? true,
        ]);
        if (! in_array($compliance['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
            $allowed = false;
            $mode = in_array($compliance['decision'], [CompliancePolicyService::REDUCE_ONLY, CompliancePolicyService::CLOSE_ONLY], true)
                ? 'REDUCE_ONLY'
                : 'NO_NEW_RISK';
            $reasons[] = 'COMPLIANCE_'.$compliance['reason_code'];
        }

        $security = $this->security->evaluate('USER', $user->id, 'EXAAI_AUTOMATION', $context['security_context'] ?? []);
        if (! in_array($security['decision'], ['ALLOW', 'ALLOW_WITH_MONITORING'], true)) {
            $allowed = false;
            $mode = in_array($security['decision'], ['EMERGENCY_LOCK', 'BLOCK'], true) ? 'NO_NEW_RISK' : $mode;
            $reasons[] = 'SECURITY_'.$security['decision'];
        }

        return [
            'allowed' => $allowed,
            'mode' => $allowed ? 'NORMAL' : $mode,
            'reasons' => array_values(array_unique($reasons)),
            'subscription' => $subscription,
            'plan' => $plan,
            'entitlements' => $entitlements,
            'usage' => [
                'allocated_capital' => $this->allocatedCapital($user),
                'active_sessions' => ExaAiSession::query()->where('user_id', $user->id)->whereIn('status', ['active', 'paused'])->count(),
            ],
            'compliance' => [
                'decision' => $compliance['decision'],
                'reason_code' => $compliance['reason_code'],
                'effective_limits' => $compliance['effective_limits'] ?? [],
            ],
            'security' => [
                'decision' => $security['decision'],
                'risk_level' => $security['risk_level'] ?? 'NONE',
                'reason_codes' => $security['reason_codes'] ?? [],
            ],
            'market' => $market,
        ];
    }

    public function assertCanUse(User $user, array $context = []): array
    {
        $effective = $this->effectiveFor($user, $context);
        if (! $effective['allowed']) {
            throw new RuntimeException('ExaAI entitlement rejected action: '.implode(',', $effective['reasons']));
        }

        return $effective;
    }

    public function updatePlanEntitlements(ExaAiPlan $plan, array $entitlements, ?int $actorId, string $reason): ExaAiPlan
    {
        return DB::transaction(function () use ($actorId, $entitlements, $plan, $reason): ExaAiPlan {
            $previous = $this->planEntitlements($plan);
            $flags = $plan->feature_flags ?? [];
            $flags['entitlements'] = array_merge($previous, $this->normalizeEntitlements($entitlements));

            $plan->forceFill([
                'feature_flags' => $flags,
                'strategy_access' => $flags['entitlements']['allowed_strategies'],
                'capital_limit' => $flags['entitlements']['maximum_ai_capital'],
                'max_open_positions' => $flags['entitlements']['maximum_positions'],
            ])->save();

            ExaAiAuditLog::query()->create([
                'user_id' => $actorId,
                'session_id' => null,
                'event_type' => 'entitlements.updated',
                'severity' => 'warning',
                'message' => 'ExaAI plan entitlements updated.',
                'context' => [
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'previous' => $previous,
                    'new' => $flags['entitlements'],
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);

            return $plan->fresh();
        });
    }

    public function planEntitlements(ExaAiPlan $plan): array
    {
        $flags = $plan->feature_flags ?? [];
        $configured = is_array($flags) ? ($flags['entitlements'] ?? []) : [];

        return array_merge($this->defaultsForPlan($plan), $this->normalizeEntitlements(is_array($configured) ? $configured : []));
    }

    private function defaultsForPlan(ExaAiPlan $plan): array
    {
        return [
            'exaai_access' => (bool) $plan->is_active,
            'maximum_ai_capital' => $this->fmt((string) $plan->capital_limit),
            'allowed_strategies' => array_values($plan->strategy_access ?? []),
            'allowed_markets' => [],
            'spot_enabled' => true,
            'futures_enabled' => in_array($plan->code, ['pro', 'elite'], true),
            'maximum_leverage' => $plan->code === 'elite' ? 10 : ($plan->code === 'pro' ? 3 : 1),
            'maximum_positions' => (int) $plan->max_open_positions,
            'market_scanning_coverage' => $plan->analytics_level,
            'signal_frequency' => $plan->execution_tier,
            'portfolio_rebalancing' => in_array($plan->code, ['pro', 'elite'], true),
            'advanced_tp_sl' => in_array($plan->code, ['pro', 'elite'], true),
            'analytics_level' => $plan->analytics_level,
            'strategy_customization' => $plan->code === 'elite',
            'api_bot_access' => $plan->code === 'elite',
            'priority_features' => $plan->code === 'elite',
        ];
    }

    private function normalizeEntitlements(array $entitlements): array
    {
        if (isset($entitlements['maximum_ai_capital'])) {
            $entitlements['maximum_ai_capital'] = $this->fmt((string) $entitlements['maximum_ai_capital']);
        }
        if (isset($entitlements['allowed_strategies']) && is_array($entitlements['allowed_strategies'])) {
            $entitlements['allowed_strategies'] = array_values(array_unique(array_map(
                fn ($strategy): string => strtolower((string) $strategy),
                $entitlements['allowed_strategies']
            )));
        }
        if (isset($entitlements['allowed_markets']) && is_array($entitlements['allowed_markets'])) {
            $entitlements['allowed_markets'] = array_values(array_unique(array_map(
                fn ($market): string => strtoupper(str_replace('/', '', (string) $market)),
                $entitlements['allowed_markets']
            )));
        }
        if (isset($entitlements['maximum_leverage'])) {
            $entitlements['maximum_leverage'] = max(1, (int) $entitlements['maximum_leverage']);
        }
        if (isset($entitlements['maximum_positions'])) {
            $entitlements['maximum_positions'] = max(0, (int) $entitlements['maximum_positions']);
        }

        return $entitlements;
    }

    private function emptyEntitlements(): array
    {
        return [
            'exaai_access' => false,
            'maximum_ai_capital' => '0.00000000',
            'allowed_strategies' => [],
            'allowed_markets' => [],
            'spot_enabled' => false,
            'futures_enabled' => false,
            'maximum_leverage' => 1,
            'maximum_positions' => 0,
            'market_scanning_coverage' => 'none',
            'signal_frequency' => 'none',
            'portfolio_rebalancing' => false,
            'advanced_tp_sl' => false,
            'analytics_level' => 'none',
            'strategy_customization' => false,
            'api_bot_access' => false,
            'priority_features' => false,
        ];
    }

    private function allocatedCapital(User $user): string
    {
        $sum = '0.00000000';
        foreach (ExaAiCapitalAllocation::query()->where('user_id', $user->id)->where('status', 'active')->get() as $allocation) {
            $sum = bcadd($sum, (string) $allocation->amount, self::SCALE);
        }

        return $sum;
    }

    private function fmt(string $value): string
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('BCMath is required for ExaAI entitlement calculations.');
        }

        return bcadd(trim($value), '0', self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }
}
