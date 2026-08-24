<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\ListingApplication;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerProfile;
use App\Models\OtcRfq;
use App\Models\Phase15ReconciliationDifference;
use App\Services\InstitutionalRiskOverviewService;
use App\Services\MarketLaunchReadinessService;
use App\Services\Phase15EmergencyControlService;
use App\Services\Phase15ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Phase15OperationsController extends Controller
{
    public function __construct(
        private readonly MarketLaunchReadinessService $launchReadiness,
        private readonly InstitutionalRiskOverviewService $riskOverview,
        private readonly Phase15ReconciliationService $reconciliation,
        private readonly Phase15EmergencyControlService $emergency,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => [
                'listings' => [
                    'approved' => ListingApplication::query()->where('application_status', 'APPROVED')->count(),
                    'pre_launch' => ListingApplication::query()->where('integration_status', 'PRE_LAUNCH')->count(),
                    'live' => ListingApplication::query()->where('integration_status', 'LIVE')->count(),
                ],
                'institutions' => [
                    'active' => InstitutionalAccount::query()->where('status', 'ACTIVE')->count(),
                ],
                'liquidity' => [
                    'active_market_makers' => MarketMakerProfile::query()->where('status', 'ACTIVE')->count(),
                    'active_bots' => MarketMakerBot::query()->where('status', 'ACTIVE')->count(),
                    'paused_bots' => MarketMakerBot::query()->whereIn('status', ['PAUSED', 'LIMIT_NEW_RISK'])->count(),
                ],
                'otc' => [
                    'open_rfqs' => OtcRfq::query()->whereIn('status', ['OPEN', 'QUOTED', 'ACCEPTED'])->count(),
                ],
                'reconciliation' => [
                    'open_differences' => Phase15ReconciliationDifference::query()->where('status', 'OPEN')->count(),
                    'critical_open_differences' => Phase15ReconciliationDifference::query()->where('status', 'OPEN')->where('severity', 'CRITICAL')->count(),
                ],
                'institutional_risk' => $this->riskOverview->overview(),
            ],
        ]);
    }

    public function listingReadiness(string $reference): JsonResponse
    {
        $application = ListingApplication::query()->where('reference', $reference)->firstOrFail();

        return response()->json(['data' => $this->launchReadiness->evaluate($application)]);
    }

    public function risk(Request $request): JsonResponse
    {
        $institution = null;
        if ($request->filled('institution_id')) {
            $institution = InstitutionalAccount::query()->findOrFail((int) $request->query('institution_id'));
        }

        return response()->json(['data' => $this->riskOverview->overview($institution)]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'string', 'max:64'],
        ]);

        $run = $this->reconciliation->run($data['scope'] ?? 'GLOBAL');

        return response()->json([
            'data' => [
                'run' => $run,
                'differences' => $run->differences()->orderByDesc('id')->limit(100)->get(),
            ],
        ], $run->status === 'PASS' ? 200 : 202);
    }

    public function emergency(Request $request): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin, 403);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['GLOBAL', 'MARKET', 'INSTITUTION', 'BOT'])],
            'scope_reference' => ['nullable', 'string', 'max:128'],
            'control' => ['required', Rule::in(['GLOBAL_LIQUIDITY_EMERGENCY', 'PAUSE_NEW_RISK', 'MARKET_HALT'])],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        if ($data['scope'] !== 'GLOBAL' && empty($data['scope_reference'])) {
            return response()->json(['message' => 'scope_reference is required for scoped emergency controls.'], 422);
        }

        $control = $this->emergency->activate(
            $admin,
            $data['scope'],
            $data['scope_reference'] ?? null,
            $data['control'],
            $data['reason'],
        );

        return response()->json(['data' => $control], 202);
    }
}
