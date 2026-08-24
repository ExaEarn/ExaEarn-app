<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\PricingDecision;
use App\Models\PricingRule;
use App\Models\PricingRuleChange;
use App\Models\PricingShadowComparison;
use App\Models\RewardPolicyDecision;
use App\Models\RewardPolicyRule;
use App\Services\PricingPolicyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class PricingRewardsController extends Controller
{
    public function __construct(private readonly PricingPolicyEngine $pricing)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'active_pricing_rules' => PricingRule::query()->where('status', 'ACTIVE')->count(),
            'pending_pricing_changes' => PricingRuleChange::query()->where('status', 'PENDING_APPROVAL')->count(),
            'pricing_decisions_24h' => PricingDecision::query()->where('created_at', '>=', now()->subDay())->count(),
            'shadow_differences_24h' => PricingShadowComparison::query()->where('status', 'DIFFERENCE')->where('created_at', '>=', now()->subDay())->count(),
            'active_reward_rules' => RewardPolicyRule::query()->where('status', 'ACTIVE')->count(),
            'blocked_reward_decisions_24h' => RewardPolicyDecision::query()->where('status', 'BLOCKED')->where('created_at', '>=', now()->subDay())->count(),
            'shadow_mode' => (bool) config('pricing.shadow_mode', true),
        ]]);
    }

    public function rules(Request $request): JsonResponse
    {
        return response()->json(['data' => PricingRule::query()
            ->when($request->query('product'), fn ($query, string $product) => $query->where('product', strtoupper($product)))
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', strtoupper($status)))
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function requestRule(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate($this->ruleValidation());

        return response()->json(['data' => $this->pricing->requestRuleChange($admin, $data)], 202);
    }

    public function approveRule(Request $request, string $changeUuid): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']]);
        $change = PricingRuleChange::query()->where('change_uuid', $changeUuid)->firstOrFail();

        return response()->json(['data' => $this->pricing->approveRuleChange($admin, $change, $data['reason'])], 201);
    }

    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:60'],
            'operation' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'asset' => ['nullable', 'string', 'max:24'],
            'currency' => ['nullable', 'string', 'max:24'],
            'network' => ['nullable', 'string', 'max:64'],
            'market_symbol' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'vip_tier' => ['nullable', 'string', 'max:24'],
            'merchant_tier' => ['nullable', 'string', 'max:40'],
            'promotion_code' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer'],
            'institution_id' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => $this->pricing->simulate($data)]);
    }

    public function decisions(Request $request): JsonResponse
    {
        return response()->json(['data' => PricingDecision::query()
            ->when($request->query('product'), fn ($query, string $product) => $query->where('product', strtoupper($product)))
            ->latest()
            ->paginate((int) $request->query('per_page', 50))]);
    }

    public function rewardRules(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $admin = $this->admin($request);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'product' => ['required', 'string', 'max:60'],
                'operation' => ['required', 'string', 'max:80'],
                'reward_type' => ['required', Rule::in(['FIXED', 'PERCENTAGE', 'REVENUE_SHARE', 'TIERED', 'MILESTONE'])],
                'value' => ['nullable', 'numeric'],
                'percentage_bps' => ['nullable', 'numeric', 'gte:0'],
                'daily_user_cap' => ['nullable', 'numeric', 'gte:0'],
                'lifetime_user_cap' => ['nullable', 'numeric', 'gte:0'],
                'campaign_budget' => ['nullable', 'numeric', 'gte:0'],
                'reward_asset' => ['nullable', 'string', 'max:24'],
                'country' => ['nullable', 'string', 'size:2'],
                'vip_tier' => ['nullable', 'string', 'max:24'],
                'promotion_code' => ['nullable', 'string', 'max:80'],
                'priority' => ['nullable', 'integer'],
                'status' => ['nullable', Rule::in(['DRAFT', 'ACTIVE', 'DISABLED', 'EXPIRED'])],
                'metadata' => ['nullable', 'array'],
            ]);

            $rule = RewardPolicyRule::query()->create(array_merge($data, [
                'rule_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'product' => strtoupper($data['product']),
                'operation' => strtoupper($data['operation']),
                'reward_type' => strtoupper($data['reward_type']),
                'reward_asset' => strtoupper($data['reward_asset'] ?? 'EXAPOINT'),
                'status' => strtoupper($data['status'] ?? 'ACTIVE'),
                'created_by_admin_id' => $admin->id,
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ]));

            return response()->json(['data' => $rule], 201);
        }

        return response()->json(['data' => RewardPolicyRule::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function rewardDecisions(Request $request): JsonResponse
    {
        return response()->json(['data' => RewardPolicyDecision::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function shadowComparisons(Request $request): JsonResponse
    {
        return response()->json(['data' => PricingShadowComparison::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            throw new RuntimeException('Admin authentication is required.');
        }

        return $admin;
    }

    private function ruleValidation(): array
    {
        return [
            'pricing_rule_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'product' => ['required', 'string', 'max:60'],
            'operation' => ['required', 'string', 'max:80'],
            'fee_type' => ['required', Rule::in(['FIXED', 'PERCENTAGE', 'HYBRID', 'SPREAD', 'TIERED', 'DYNAMIC', 'WAIVED', 'REBATE', 'CUSTOM_CONTRACT'])],
            'value' => ['nullable', 'numeric'],
            'fixed_value' => ['nullable', 'numeric'],
            'percentage_bps' => ['nullable', 'numeric'],
            'spread_bps' => ['nullable', 'numeric'],
            'min_fee' => ['nullable', 'numeric'],
            'max_fee' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:24'],
            'asset' => ['nullable', 'string', 'max:24'],
            'network' => ['nullable', 'string', 'max:64'],
            'market_symbol' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'vip_tier' => ['nullable', 'string', 'max:24'],
            'merchant_tier' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'integer'],
            'institution_id' => ['nullable', 'integer'],
            'promotion_code' => ['nullable', 'string', 'max:80'],
            'precedence_scope' => ['required', Rule::in(array_keys((array) config('pricing.precedence', [])))],
            'priority' => ['nullable', 'integer'],
            'version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'SCHEDULED', 'DISABLED'])],
            'allow_negative' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ];
    }
}
