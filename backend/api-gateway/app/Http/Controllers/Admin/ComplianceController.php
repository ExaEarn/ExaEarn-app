<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ComplianceCase;
use App\Models\ComplianceDecisionLog;
use App\Models\ComplianceJurisdiction;
use App\Models\CompliancePolicyChange;
use App\Models\CompliancePolicyRule;
use App\Models\ComplianceProduct;
use App\Models\ComplianceUserRestriction;
use App\Models\User;
use App\Services\CompliancePolicyAdminService;
use App\Services\CompliancePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplianceController extends Controller
{
    public function __construct(
        private readonly CompliancePolicyService $policy,
        private readonly CompliancePolicyAdminService $adminPolicy,
    ) {
    }

    public function overview(): JsonResponse
    {
        $this->policy->seedDefaults();

        return response()->json(['data' => [
            'jurisdictions' => ComplianceJurisdiction::query()->count(),
            'products' => ComplianceProduct::query()->count(),
            'active_rules' => CompliancePolicyRule::query()->where('status', 'ACTIVE')->count(),
            'pending_policy_changes' => CompliancePolicyChange::query()->where('status', 'PENDING_APPROVAL')->count(),
            'active_user_restrictions' => ComplianceUserRestriction::query()->where('status', 'ACTIVE')->count(),
            'open_cases' => ComplianceCase::query()->whereIn('status', ['OPEN', 'REVIEWING', 'ESCALATED'])->count(),
            'recent_denials' => ComplianceDecisionLog::query()->whereIn('decision', ['DENY', 'SUSPENDED'])->where('decided_at', '>=', now()->subDay())->count(),
            'policy_version' => config('compliance.policy_version'),
        ]]);
    }

    public function products(): JsonResponse
    {
        $this->policy->seedDefaults();

        return response()->json(['data' => ComplianceProduct::query()->orderBy('product_code')->get()]);
    }

    public function jurisdictions(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'country_code' => ['required', 'string', 'size:2'],
                'name' => ['required', 'string', 'max:120'],
                'status' => ['required', Rule::in(['SUPPORTED', 'RESTRICTED', 'BLOCKED', 'REVIEW_REQUIRED'])],
                'risk_tier' => ['nullable', 'string', 'max:32'],
                'allowed_products' => ['nullable', 'array'],
                'blocked_products' => ['nullable', 'array'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);
            $row = ComplianceJurisdiction::query()->updateOrCreate(
                ['country_code' => strtoupper((string) $data['country_code'])],
                [
                    'country_name' => $data['name'],
                    'status' => $data['status'],
                    'risk_level' => $data['risk_tier'] ?? 'STANDARD',
                    'metadata' => [
                        'allowed_products' => array_map('strtoupper', $data['allowed_products'] ?? []),
                        'blocked_products' => array_map('strtoupper', $data['blocked_products'] ?? []),
                        'notes' => $data['notes'] ?? null,
                    ],
                ],
            );

            return response()->json(['data' => $row->fresh()], 201);
        }

        return response()->json(['data' => ComplianceJurisdiction::query()->orderBy('country_code')->paginate((int) $request->query('per_page', 50))]);
    }

    public function rules(Request $request): JsonResponse
    {
        return response()->json(['data' => CompliancePolicyRule::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function submitRule(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $this->validateRule($request);
        $change = $this->adminPolicy->submitRuleChange($admin, $data, $request);

        return response()->json(['data' => $change], 202);
    }

    public function approveChange(Request $request, int $changeId): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']]);
        $rule = $this->adminPolicy->approveChange($admin, CompliancePolicyChange::query()->findOrFail($changeId), $data['reason'], $request);

        return response()->json(['data' => $rule], 201);
    }

    public function rejectChange(Request $request, int $changeId): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:1000']]);
        $change = $this->adminPolicy->rejectChange($admin, CompliancePolicyChange::query()->findOrFail($changeId), $data['reason'], $request);

        return response()->json(['data' => $change], 202);
    }

    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'product_code' => ['required', 'string', 'max:64'],
            'action' => ['nullable', 'string', 'max:64'],
            'jurisdiction' => ['nullable', 'string', 'size:2'],
            'account_type' => ['nullable', 'string', 'max:64'],
            'asset' => ['nullable', 'string', 'max:32'],
            'market_symbol' => ['nullable', 'string', 'max:32'],
            'network' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:16'],
            'requested_leverage' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        return response()->json(['data' => $this->policy->simulate($data)]);
    }

    public function impact(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->policy->impact($request->all())]);
    }

    public function userEligibility(int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        return response()->json(['data' => $this->policy->getProductEligibility($user)]);
    }

    public function emergency(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_code' => ['nullable', 'string', 'max:64'],
            'jurisdiction' => ['nullable', 'string', 'size:2'],
            'market_symbol' => ['nullable', 'string', 'max:32'],
            'asset' => ['nullable', 'string', 'max:32'],
            'decision' => ['required', Rule::in(['DENY', 'REDUCE_ONLY', 'CLOSE_ONLY', 'SELL_ONLY', 'WITHDRAW_ONLY', 'SUSPENDED'])],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $change = $this->adminPolicy->submitRuleChange($admin, array_merge($data, [
            'reason_code' => 'EMERGENCY_COMPLIANCE_CONTROL',
            'required_kyc_level' => 0,
            'precedence' => 10000,
            'effective_at' => now(),
            'metadata' => ['emergency' => true],
        ]), $request);

        return response()->json(['data' => $change], 202);
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'jurisdiction' => ['nullable', 'string', 'size:2'],
            'product_code' => ['nullable', 'string', 'max:64'],
            'account_type' => ['nullable', 'string', 'max:64'],
            'asset' => ['nullable', 'string', 'max:32'],
            'market_symbol' => ['nullable', 'string', 'max:32'],
            'network' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:16'],
            'decision' => ['required', Rule::in(['ALLOW', 'DENY', 'REQUIRE_KYC', 'REQUIRE_KYB', 'REQUIRE_ENHANCED_REVIEW', 'REDUCE_ONLY', 'CLOSE_ONLY', 'SELL_ONLY', 'WITHDRAW_ONLY', 'SUSPENDED'])],
            'reason_code' => ['required', 'string', 'max:120'],
            'required_kyc_level' => ['nullable', 'integer', 'min:0', 'max:5'],
            'required_kyb_tier' => ['nullable', 'string', 'max:64'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'max_leverage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'precedence' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'limits' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'effective_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->hasPermission('compliance.manage'), 403);

        return $admin;
    }
}
