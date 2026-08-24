<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExaAiMarketEligibility;
use App\Models\ExaAiPlan;
use App\Models\ExaAiPublicSetting;
use App\Models\ExaAiStrategyVersion;
use App\Models\ExaAiSurveillanceCase;
use App\Models\User;
use App\Services\ExaAiEntitlementService;
use App\Services\ExaAiOperationalReadinessService;
use App\Services\ExaAiOperationsService;
use App\Services\ExaAiService;
use App\Services\ExaAiStrategyGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExaAiAdminController extends Controller
{
    public function __construct(
        private readonly ExaAiService $exaAi,
        private readonly ExaAiOperationalReadinessService $readiness,
        private readonly ExaAiOperationsService $operations,
        private readonly ExaAiStrategyGovernanceService $governance,
        private readonly ExaAiEntitlementService $entitlements,
    )
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminOverview()]);
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => $this->exaAi->adminPlans()->map(function (ExaAiPlan $plan): array {
                return array_merge($plan->toArray(), [
                    'effective_entitlements' => $this->entitlements->planEntitlements($plan),
                ]);
            })->values(),
        ]);
    }

    public function strategies(): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminStrategies()]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminSessions((int) $request->query('per_page', 25))]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminSubscriptions((int) $request->query('per_page', 25))]);
    }

    public function trades(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminTrades((int) $request->query('per_page', 25))]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exaAi->adminAuditLogs((int) $request->query('per_page', 25))]);
    }

    public function readiness(): JsonResponse
    {
        return response()->json(['data' => $this->readiness->report()]);
    }

    public function operationsReadiness(): JsonResponse
    {
        return response()->json(['data' => $this->operations->evaluate()]);
    }

    public function marketEligibilityStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'product' => ['required', 'string', 'in:spot,futures'],
            'status' => ['required', 'string', 'in:enabled,disabled,paused'],
            'risk_tier' => ['nullable', 'string', 'max:40'],
            'min_liquidity' => ['nullable', 'numeric', 'gte:0'],
            'max_exposure' => ['nullable', 'numeric', 'gte:0'],
            'max_concentration_percent' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'max_slippage_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'market_data_freshness_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'metadata' => ['nullable', 'array'],
        ]);

        $market = ExaAiMarketEligibility::query()->updateOrCreate([
            'symbol' => strtoupper(str_replace('/', '', (string) $payload['symbol'])),
            'product' => strtolower((string) $payload['product']),
        ], [
            'status' => $payload['status'],
            'risk_tier' => $payload['risk_tier'] ?? 'standard',
            'min_liquidity' => $payload['min_liquidity'] ?? '0',
            'max_exposure' => $payload['max_exposure'] ?? '0',
            'max_concentration_percent' => $payload['max_concentration_percent'] ?? '25',
            'max_slippage_bps' => $payload['max_slippage_bps'] ?? 50,
            'market_data_freshness_seconds' => $payload['market_data_freshness_seconds'] ?? 30,
            'metadata' => $payload['metadata'] ?? [],
        ]);

        return response()->json(['data' => $market], 201);
    }

    public function controls(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'global_kill_switch' => ['required', 'boolean'],
            'state' => ['nullable', 'string', 'in:NORMAL,NEW_RISK_DISABLED,REDUCE_ONLY,PAUSED,EMERGENCY'],
            'disable_new_sessions' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $setting = ExaAiPublicSetting::query()->updateOrCreate([
            'key' => 'global_controls',
        ], [
            'value' => [
                'global_kill_switch' => (bool) $payload['global_kill_switch'],
                'state' => $payload['state'] ?? ((bool) $payload['global_kill_switch'] ? 'EMERGENCY' : 'NORMAL'),
                'disable_new_sessions' => (bool) ($payload['disable_new_sessions'] ?? false),
                'reason' => $payload['reason'],
                'updated_by' => $request->user()?->id,
                'updated_at' => now()->toISOString(),
            ],
        ]);

        return response()->json(['data' => $setting]);
    }

    public function surveillanceCases(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ExaAiSurveillanceCase::query()
                ->orderByDesc('id')
                ->paginate((int) $request->query('per_page', 25)),
        ]);
    }

    public function safeResume(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return response()->json(['data' => $this->operations->safeResume($request->user()?->id, $payload['reason'])]);
    }

    public function expireStaleDecisions(): JsonResponse
    {
        return response()->json(['data' => ['expired' => $this->operations->expireStaleDecisions()]]);
    }

    public function autoDisableMarkets(): JsonResponse
    {
        return response()->json(['data' => ['disabled' => $this->operations->autoDisableUnsafeMarkets()]]);
    }

    public function transitionStrategy(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'state' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $version = ExaAiStrategyVersion::query()->findOrFail($id);

        return response()->json([
            'data' => $this->governance->transition($version, $payload['state'], $payload['reason'], $request->user()?->id),
        ]);
    }

    public function updatePlanEntitlements(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'entitlements' => ['required', 'array'],
            'entitlements.exaai_access' => ['nullable', 'boolean'],
            'entitlements.maximum_ai_capital' => ['nullable', 'numeric', 'gte:0'],
            'entitlements.allowed_strategies' => ['nullable', 'array'],
            'entitlements.allowed_strategies.*' => ['string', 'max:40'],
            'entitlements.allowed_markets' => ['nullable', 'array'],
            'entitlements.allowed_markets.*' => ['string', 'max:40'],
            'entitlements.spot_enabled' => ['nullable', 'boolean'],
            'entitlements.futures_enabled' => ['nullable', 'boolean'],
            'entitlements.maximum_leverage' => ['nullable', 'integer', 'min:1', 'max:125'],
            'entitlements.maximum_positions' => ['nullable', 'integer', 'min:0', 'max:500'],
            'entitlements.market_scanning_coverage' => ['nullable', 'string', 'max:40'],
            'entitlements.signal_frequency' => ['nullable', 'string', 'max:40'],
            'entitlements.portfolio_rebalancing' => ['nullable', 'boolean'],
            'entitlements.advanced_tp_sl' => ['nullable', 'boolean'],
            'entitlements.analytics_level' => ['nullable', 'string', 'max:40'],
            'entitlements.strategy_customization' => ['nullable', 'boolean'],
            'entitlements.api_bot_access' => ['nullable', 'boolean'],
            'entitlements.priority_features' => ['nullable', 'boolean'],
        ]);

        $plan = ExaAiPlan::query()->findOrFail($id);

        return response()->json([
            'data' => $this->entitlements->updatePlanEntitlements(
                $plan,
                $payload['entitlements'],
                $request->user()?->id,
                $payload['reason']
            ),
        ]);
    }

    public function userEntitlements(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return response()->json(['data' => $this->entitlements->effectiveFor($user)]);
    }
}
