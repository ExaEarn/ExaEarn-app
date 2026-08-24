<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\PricingDecision;
use App\Models\PricingRule;
use App\Models\PricingRuleChange;
use App\Models\PricingShadowComparison;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PricingPolicyEngine
{
    private const SCALE = 18;

    /**
     * @return array<string, mixed>
     */
    public function preview(array $context): array
    {
        $context = $this->normalizeContext($context);
        $rule = $this->selectRule($context);
        $amount = $context['amount'];
        $fee = $this->calculateFee($rule, $amount);
        $rebate = FinancialDecimal::compare($fee, '0') < 0 ? FinancialDecimal::abs($fee) : '0';
        $positiveFee = FinancialDecimal::compare($fee, '0') < 0 ? '0' : $fee;

        return [
            'source' => 'PRICING_ENGINE',
            'product' => $context['product'],
            'operation' => $context['operation'],
            'asset' => $context['asset'] ?? $context['currency'] ?? null,
            'currency' => $context['currency'] ?? $context['asset'] ?? null,
            'gross_amount' => FinancialDecimal::normalize($amount, self::SCALE),
            'fee_amount' => FinancialDecimal::normalize($positiveFee, self::SCALE),
            'rebate_amount' => FinancialDecimal::normalize($rebate, self::SCALE),
            'discount_amount' => '0.000000000000000000',
            'net_amount' => FinancialDecimal::normalize(FinancialDecimal::sub($amount, $positiveFee, self::SCALE), self::SCALE),
            'rate_bps' => (string) $rule->percentage_bps,
            'spread_bps' => (string) $rule->spread_bps,
            'fixed_fee' => FinancialDecimal::normalize((string) $rule->fixed_value, self::SCALE),
            'fee_type' => $rule->fee_type,
            'pricing_rule_id' => $rule->id,
            'rule_uuid' => $rule->rule_uuid,
            'rule_version' => $rule->version,
            'precedence_scope' => $rule->precedence_scope,
            'expires_at' => now()->addSeconds((int) config('pricing.quote_ttl_seconds', 30))->toISOString(),
            'fee_policy_snapshot' => $this->snapshot($rule),
        ];
    }

    public function isEnforced(string $product): bool
    {
        return in_array(strtoupper($product), array_map('strtoupper', (array) config('pricing.enforced_products', [])), true);
    }

    public function quote(?User $user, array $context): PricingDecision
    {
        $preview = $this->preview(array_merge($context, ['user_id' => $user?->id ?? $context['user_id'] ?? null]));

        return PricingDecision::query()->create([
            'decision_uuid' => (string) Str::uuid(),
            'user_id' => $user?->id ?? $context['user_id'] ?? null,
            'institution_id' => $context['institution_id'] ?? null,
            'pricing_rule_id' => $preview['pricing_rule_id'],
            'rule_version' => $preview['rule_version'],
            'product' => $preview['product'],
            'operation' => $preview['operation'],
            'fee_type' => $preview['fee_type'],
            'gross_amount' => $preview['gross_amount'],
            'fee_amount' => $preview['fee_amount'],
            'discount_amount' => $preview['discount_amount'],
            'rebate_amount' => $preview['rebate_amount'],
            'network_fee_amount' => $context['network_fee_amount'] ?? '0',
            'provider_fee_amount' => $context['provider_fee_amount'] ?? '0',
            'net_amount' => $preview['net_amount'],
            'currency' => $preview['currency'],
            'asset' => $preview['asset'],
            'status' => 'QUOTED',
            'source' => 'PRICING_ENGINE',
            'context' => $context,
            'rule_snapshot' => $preview['fee_policy_snapshot'],
            'decided_at' => now(),
            'expires_at' => now()->addSeconds((int) config('pricing.quote_ttl_seconds', 30)),
        ]);
    }

    public function simulate(array $context): array
    {
        return [
            'preview' => $this->preview($context),
            'mutation' => 'NONE',
            'safe_for_shadow_mode' => true,
        ];
    }

    public function recordShadowComparison(string $product, string $operation, string $legacyFee, string $engineFee, array $context = []): PricingShadowComparison
    {
        $legacy = FinancialDecimal::normalize($legacyFee, self::SCALE);
        $engine = FinancialDecimal::normalize($engineFee, self::SCALE);
        $difference = FinancialDecimal::sub($engine, $legacy, self::SCALE);

        return PricingShadowComparison::query()->create([
            'comparison_uuid' => (string) Str::uuid(),
            'product' => strtoupper($product),
            'operation' => strtoupper($operation),
            'legacy_fee_amount' => $legacy,
            'engine_fee_amount' => $engine,
            'difference_amount' => $difference,
            'status' => FinancialDecimal::compare($difference, '0', self::SCALE) === 0 ? 'MATCH' : 'DIFFERENCE',
            'context' => $context,
        ]);
    }

    public function requestRuleChange(Admin $admin, array $payload): PricingRuleChange
    {
        $this->validateRulePayload($payload);
        $rule = isset($payload['pricing_rule_id']) ? PricingRule::query()->find($payload['pricing_rule_id']) : null;

        return PricingRuleChange::query()->create([
            'change_uuid' => (string) Str::uuid(),
            'pricing_rule_id' => $rule?->id,
            'action' => $rule ? 'UPDATE_RULE' : 'CREATE_RULE',
            'status' => 'PENDING_APPROVAL',
            'requested_by_admin_id' => $admin->id,
            'previous_value' => $rule?->toArray(),
            'new_value' => $this->normalizeRulePayload($payload),
            'impact_preview' => $this->impactPreview($payload),
            'reason' => (string) ($payload['reason'] ?? 'Commercial policy change request.'),
        ]);
    }

    public function approveRuleChange(Admin $admin, PricingRuleChange $change, string $reason): PricingRule
    {
        if ($change->requested_by_admin_id === $admin->id) {
            throw new RuntimeException('Maker-checker violation: requester cannot approve the same pricing change.');
        }

        return DB::transaction(function () use ($admin, $change, $reason): PricingRule {
            $change = PricingRuleChange::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();
            if ($change->status !== 'PENDING_APPROVAL') {
                throw new RuntimeException('Pricing change is not pending approval.');
            }

            $payload = (array) $change->new_value;
            $values = array_merge($payload, [
                'rule_uuid' => $payload['rule_uuid'] ?? (string) Str::uuid(),
                'status' => $payload['status'] ?? 'ACTIVE',
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
                'version' => (int) ($payload['version'] ?? 1),
            ]);
            $rule = $change->pricing_rule_id
                ? tap(PricingRule::query()->findOrFail($change->pricing_rule_id))->forceFill($values)
                : new PricingRule($values);
            $rule->save();

            $change->forceFill([
                'pricing_rule_id' => $rule->id,
                'status' => 'APPROVED',
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
                'approval_reason' => $reason,
            ])->save();

            $this->invalidateCache();

            return $rule->fresh();
        });
    }

    public function invalidateCache(): void
    {
        Cache::forget('pricing_rules.active');
    }

    private function selectRule(array $context): PricingRule
    {
        /** @var Collection<int, PricingRule> $rules */
        $rules = Cache::remember('pricing_rules.active', (int) config('pricing.cache_ttl_seconds', 60), function (): Collection {
            return PricingRule::query()
                ->where('status', 'ACTIVE')
                ->where(function ($query): void {
                    $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
                })
                ->get();
        });

        $match = $rules
            ->filter(fn (PricingRule $rule): bool => $this->matches($rule, $context))
            ->sortByDesc(fn (PricingRule $rule): array => [
                (int) config('pricing.precedence.'.$rule->precedence_scope, 0),
                (int) $rule->priority,
                (int) $rule->version,
                (int) $rule->id,
            ])
            ->first();

        if (!$match) {
            throw new RuntimeException("No active pricing rule for {$context['product']}:{$context['operation']}.");
        }

        return $match;
    }

    private function matches(PricingRule $rule, array $context): bool
    {
        foreach (['product', 'operation'] as $field) {
            if (strtoupper((string) $rule->{$field}) !== strtoupper((string) $context[$field])) {
                return false;
            }
        }

        foreach (['currency', 'asset', 'network', 'market_symbol', 'country', 'vip_tier', 'merchant_tier', 'promotion_code'] as $field) {
            $ruleValue = $rule->{$field};
            if ($ruleValue !== null && strtoupper((string) $ruleValue) !== strtoupper((string) ($context[$field] ?? ''))) {
                return false;
            }
        }

        foreach (['user_id', 'institution_id'] as $field) {
            if ($rule->{$field} !== null && (int) $rule->{$field} !== (int) ($context[$field] ?? 0)) {
                return false;
            }
        }

        foreach ((array) data_get($rule->metadata ?? [], 'pricing_dimensions', []) as $field => $expected) {
            if ($expected !== null && strtoupper((string) $expected) !== strtoupper((string) ($context[$field] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private function calculateFee(PricingRule $rule, string $amount): string
    {
        $fee = match (strtoupper($rule->fee_type)) {
            'FIXED' => $this->fixed($rule),
            'PERCENTAGE' => $this->bps($amount, (string) $rule->percentage_bps),
            'HYBRID' => FinancialDecimal::add($this->fixed($rule), $this->bps($amount, (string) $rule->percentage_bps), self::SCALE),
            'SPREAD' => $this->bps($amount, (string) $rule->spread_bps),
            'TIERED', 'DYNAMIC' => $this->tieredFee($rule, $amount),
            'WAIVED' => '0',
            'REBATE', 'CUSTOM_CONTRACT' => $this->customFee($rule, $amount),
            default => throw new RuntimeException('Unsupported pricing fee type.'),
        };

        if (FinancialDecimal::compare($fee, '0', self::SCALE) < 0 && !$rule->allow_negative) {
            throw new RuntimeException('Negative fees require an explicit approved rebate rule.');
        }

        if (FinancialDecimal::compare($fee, '0', self::SCALE) > 0) {
            if ($rule->min_fee !== null) {
                $fee = FinancialDecimal::max($fee, (string) $rule->min_fee, self::SCALE);
            }
            if ($rule->max_fee !== null) {
                $fee = FinancialDecimal::min($fee, (string) $rule->max_fee, self::SCALE);
            }
        }

        $this->assertGuardrails($rule, $fee);

        return FinancialDecimal::normalize($fee, self::SCALE);
    }

    private function fixed(PricingRule $rule): string
    {
        return FinancialDecimal::compare((string) $rule->fixed_value, '0', self::SCALE) !== 0
            ? (string) $rule->fixed_value
            : (string) $rule->value;
    }

    private function customFee(PricingRule $rule, string $amount): string
    {
        $fixed = $this->fixed($rule);
        $percentage = $this->bps($amount, (string) $rule->percentage_bps);

        return FinancialDecimal::add($fixed, $percentage, self::SCALE);
    }

    private function tieredFee(PricingRule $rule, string $amount): string
    {
        $tiers = (array) data_get($rule->metadata ?? [], 'tiers', []);
        foreach ($tiers as $tier) {
            $min = (string) ($tier['min_amount'] ?? '0');
            $max = $tier['max_amount'] ?? null;
            if (FinancialDecimal::compare($amount, $min, self::SCALE) >= 0 && ($max === null || FinancialDecimal::compare($amount, (string) $max, self::SCALE) <= 0)) {
                return $this->bps($amount, (string) ($tier['percentage_bps'] ?? $rule->percentage_bps));
            }
        }

        return $this->bps($amount, (string) $rule->percentage_bps);
    }

    private function bps(string $amount, string $bps): string
    {
        return FinancialDecimal::div(FinancialDecimal::mul($amount, $bps, self::SCALE), '10000', self::SCALE);
    }

    private function normalizeContext(array $context): array
    {
        foreach (['product', 'operation'] as $field) {
            if (empty($context[$field])) {
                throw new RuntimeException("Pricing {$field} is required.");
            }
            $context[$field] = strtoupper((string) $context[$field]);
        }

        $context['amount'] = FinancialDecimal::normalize((string) ($context['amount'] ?? $context['gross_amount'] ?? '0'), self::SCALE);
        if (FinancialDecimal::compare($context['amount'], '0', self::SCALE) <= 0) {
            throw new RuntimeException('Pricing amount must be greater than zero.');
        }

        foreach (['currency', 'asset', 'network', 'market_symbol', 'country', 'vip_tier', 'merchant_tier', 'promotion_code'] as $field) {
            if (isset($context[$field]) && $context[$field] !== null) {
                $context[$field] = strtoupper((string) $context[$field]);
            }
        }

        return $context;
    }

    private function validateRulePayload(array $payload): void
    {
        $normalized = $this->normalizeRulePayload($payload);
        $rule = new PricingRule($normalized);
        $this->assertGuardrails($rule, $this->calculateFee($rule, '100'));
    }

    private function normalizeRulePayload(array $payload): array
    {
        $payload['product'] = strtoupper((string) ($payload['product'] ?? ''));
        $payload['operation'] = strtoupper((string) ($payload['operation'] ?? ''));
        $payload['fee_type'] = strtoupper((string) ($payload['fee_type'] ?? ''));
        $payload['precedence_scope'] = strtoupper((string) ($payload['precedence_scope'] ?? 'PRODUCT_DEFAULT'));
        $payload['status'] = strtoupper((string) ($payload['status'] ?? 'ACTIVE'));
        $payload['name'] = (string) ($payload['name'] ?? "{$payload['product']} {$payload['operation']} policy");
        $payload['value'] = FinancialDecimal::normalize((string) ($payload['value'] ?? '0'), self::SCALE);
        $payload['fixed_value'] = FinancialDecimal::normalize((string) ($payload['fixed_value'] ?? '0'), self::SCALE);
        $payload['percentage_bps'] = FinancialDecimal::normalize((string) ($payload['percentage_bps'] ?? '0'), 8);
        $payload['spread_bps'] = FinancialDecimal::normalize((string) ($payload['spread_bps'] ?? '0'), 8);
        $payload['priority'] = (int) ($payload['priority'] ?? 0);
        $payload['version'] = (int) ($payload['version'] ?? 1);
        $payload['allow_negative'] = (bool) ($payload['allow_negative'] ?? false);
        $payload['requires_maker_checker'] = (bool) ($payload['requires_maker_checker'] ?? true);

        foreach (['currency', 'asset', 'network', 'market_symbol', 'country', 'vip_tier', 'merchant_tier', 'promotion_code'] as $field) {
            $payload[$field] = isset($payload[$field]) && $payload[$field] !== null ? strtoupper((string) $payload[$field]) : null;
        }

        foreach (['min_fee', 'max_fee'] as $field) {
            $payload[$field] = isset($payload[$field]) && $payload[$field] !== null ? FinancialDecimal::normalize((string) $payload[$field], self::SCALE) : null;
        }

        return $payload;
    }

    private function assertGuardrails(PricingRule $rule, string $fee): void
    {
        if (FinancialDecimal::compare((string) $rule->percentage_bps, (string) config('pricing.guardrails.max_percentage_bps', '1000'), 8) > 0) {
            throw new RuntimeException('Pricing percentage exceeds configured guardrail.');
        }
        if (FinancialDecimal::compare((string) $rule->spread_bps, (string) config('pricing.guardrails.max_spread_bps', '1000'), 8) > 0) {
            throw new RuntimeException('Pricing spread exceeds configured guardrail.');
        }
        if (FinancialDecimal::compare(FinancialDecimal::abs($fee, self::SCALE), (string) config('pricing.guardrails.max_fixed_fee', '1000000'), self::SCALE) > 0) {
            throw new RuntimeException('Pricing fee exceeds configured absolute guardrail.');
        }
    }

    private function impactPreview(array $payload): array
    {
        return [
            'estimated_affected_products' => [strtoupper((string) ($payload['product'] ?? 'UNKNOWN'))],
            'requires_finance_review' => true,
            'safe_to_apply_without_settlement_mutation' => true,
        ];
    }

    private function snapshot(PricingRule $rule): array
    {
        return [
            'rule_uuid' => $rule->rule_uuid,
            'version' => $rule->version,
            'name' => $rule->name,
            'product' => $rule->product,
            'operation' => $rule->operation,
            'fee_type' => $rule->fee_type,
            'precedence_scope' => $rule->precedence_scope,
            'priority' => $rule->priority,
            'value' => (string) $rule->value,
            'fixed_value' => (string) $rule->fixed_value,
            'percentage_bps' => (string) $rule->percentage_bps,
            'spread_bps' => (string) $rule->spread_bps,
            'min_fee' => $rule->min_fee === null ? null : (string) $rule->min_fee,
            'max_fee' => $rule->max_fee === null ? null : (string) $rule->max_fee,
            'dimensions' => [
                'currency' => $rule->currency,
                'asset' => $rule->asset,
                'network' => $rule->network,
                'market_symbol' => $rule->market_symbol,
                'country' => $rule->country,
                'vip_tier' => $rule->vip_tier,
                'merchant_tier' => $rule->merchant_tier,
                'user_id' => $rule->user_id,
                'institution_id' => $rule->institution_id,
                'promotion_code' => $rule->promotion_code,
            ],
        ];
    }
}
