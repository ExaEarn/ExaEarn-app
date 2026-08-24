<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RewardPolicyDecision;
use App\Models\RewardPolicyRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RewardPolicyEngine
{
    public function __construct(private readonly RewardSecurityService $security)
    {
    }

    public function decide(?User $user, array $context): RewardPolicyDecision
    {
        $context = $this->normalizeContext($context);
        $rule = $this->selectRule($context);
        $reward = $this->calculateReward($rule, $context['amount']);
        [$status, $reason] = $this->guardReward($user, $rule, $reward, $context);

        return DB::transaction(function () use ($user, $context, $rule, $reward, $status, $reason): RewardPolicyDecision {
            if ($status === 'APPROVED' && $rule->campaign_budget !== null) {
                $nextSpent = FinancialDecimal::add((string) $rule->campaign_spent, $reward);
                if (FinancialDecimal::compare($nextSpent, (string) $rule->campaign_budget) > 0) {
                    $status = 'BLOCKED';
                    $reason = 'CAMPAIGN_BUDGET_EXCEEDED';
                } else {
                    $rule->forceFill(['campaign_spent' => $nextSpent])->save();
                }
            }

            return RewardPolicyDecision::query()->create([
                'decision_uuid' => (string) Str::uuid(),
                'reward_policy_rule_id' => $rule->id,
                'user_id' => $user?->id ?? $context['user_id'] ?? null,
                'product' => $context['product'],
                'operation' => $context['operation'],
                'gross_amount' => $context['amount'],
                'reward_amount' => $reward,
                'reward_asset' => $rule->reward_asset,
                'status' => $status,
                'reason_code' => $reason,
                'context' => $context,
                'rule_snapshot' => $rule->toArray(),
                'decided_at' => now(),
            ]);
        });
    }

    private function selectRule(array $context): RewardPolicyRule
    {
        $rule = RewardPolicyRule::query()
            ->where('status', 'ACTIVE')
            ->where('product', $context['product'])
            ->where('operation', $context['operation'])
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
            })
            ->get()
            ->filter(function (RewardPolicyRule $rule) use ($context): bool {
                foreach (['country', 'vip_tier', 'promotion_code'] as $field) {
                    if ($rule->{$field} !== null && strtoupper((string) $rule->{$field}) !== strtoupper((string) ($context[$field] ?? ''))) {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc(fn (RewardPolicyRule $rule): array => [(int) $rule->priority, (int) $rule->version, (int) $rule->id])
            ->first();

        if (!$rule) {
            throw new RuntimeException("No active reward policy for {$context['product']}:{$context['operation']}.");
        }

        return $rule;
    }

    private function calculateReward(RewardPolicyRule $rule, string $amount): string
    {
        $reward = match (strtoupper($rule->reward_type)) {
            'FIXED', 'MILESTONE' => (string) $rule->value,
            'PERCENTAGE', 'REVENUE_SHARE', 'TIERED' => FinancialDecimal::div(FinancialDecimal::mul($amount, (string) $rule->percentage_bps), '10000'),
            default => throw new RuntimeException('Unsupported reward policy type.'),
        };

        if (FinancialDecimal::compare($reward, '0') < 0) {
            throw new RuntimeException('Rewards cannot be negative.');
        }

        return FinancialDecimal::normalize($reward);
    }

    private function guardReward(?User $user, RewardPolicyRule $rule, string $reward, array $context): array
    {
        if (!$user) {
            return ['PENDING_REVIEW', 'USER_REQUIRED'];
        }

        $flags = $this->security->inspect($user, $context['operation'], $context);
        if ($flags !== []) {
            return ['BLOCKED', 'REWARD_ABUSE_FLAGS'];
        }

        if ($rule->daily_user_cap !== null) {
            $issuedToday = (string) RewardPolicyDecision::query()
                ->where('user_id', $user->id)
                ->where('reward_policy_rule_id', $rule->id)
                ->whereDate('created_at', today())
                ->where('status', 'APPROVED')
                ->sum('reward_amount');

            if (FinancialDecimal::compare(FinancialDecimal::add($issuedToday, $reward), (string) $rule->daily_user_cap) > 0) {
                return ['BLOCKED', 'DAILY_REWARD_CAP_EXCEEDED'];
            }
        }

        if ($rule->lifetime_user_cap !== null) {
            $issued = (string) RewardPolicyDecision::query()
                ->where('user_id', $user->id)
                ->where('reward_policy_rule_id', $rule->id)
                ->where('status', 'APPROVED')
                ->sum('reward_amount');

            if (FinancialDecimal::compare(FinancialDecimal::add($issued, $reward), (string) $rule->lifetime_user_cap) > 0) {
                return ['BLOCKED', 'LIFETIME_REWARD_CAP_EXCEEDED'];
            }
        }

        return ['APPROVED', null];
    }

    private function normalizeContext(array $context): array
    {
        $context['product'] = strtoupper((string) ($context['product'] ?? ''));
        $context['operation'] = strtoupper((string) ($context['operation'] ?? ''));
        if ($context['product'] === '' || $context['operation'] === '') {
            throw new RuntimeException('Reward product and operation are required.');
        }
        $context['amount'] = FinancialDecimal::normalize((string) ($context['amount'] ?? $context['gross_amount'] ?? '0'));
        if (FinancialDecimal::compare($context['amount'], '0') < 0) {
            throw new RuntimeException('Reward amount basis cannot be negative.');
        }
        foreach (['country', 'vip_tier', 'promotion_code'] as $field) {
            if (isset($context[$field]) && $context[$field] !== null) {
                $context[$field] = strtoupper((string) $context[$field]);
            }
        }

        return $context;
    }
}
