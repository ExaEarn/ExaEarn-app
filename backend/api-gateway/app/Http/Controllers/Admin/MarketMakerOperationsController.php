<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketMakerIncident;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerProgramApplication;
use App\Models\MarketMakerRebatePeriod;
use App\Models\MarketMakerSurveillanceCase;
use App\Services\MarketLiquidityHealthService;
use App\Services\MarketMakerInventoryService;
use App\Services\MarketMakerMassCancelService;
use App\Services\MarketMakerProgramService;
use App\Services\MarketMakerRebateService;
use App\Services\MarketMakerSurveillanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MarketMakerOperationsController extends Controller
{
    public function __construct(
        private readonly MarketMakerProgramService $program,
        private readonly MarketMakerInventoryService $inventory,
        private readonly MarketLiquidityHealthService $health,
        private readonly MarketMakerRebateService $rebates,
        private readonly MarketMakerSurveillanceService $surveillance,
        private readonly MarketMakerMassCancelService $massCancel,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'applications_pending' => MarketMakerProgramApplication::query()->whereNotIn('status', ['ACTIVE', 'REJECTED', 'OFFBOARDED'])->count(),
            'active_profiles' => MarketMakerProfile::query()->where('status', 'ACTIVE')->count(),
            'active_assignments' => MarketMakerMarketAssignment::query()->where('status', 'ACTIVE')->count(),
            'open_incidents' => MarketMakerIncident::query()->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count(),
            'open_surveillance_cases' => MarketMakerSurveillanceCase::query()->where('status', 'OPEN')->count(),
            'phase15c_controls' => [
                'dedicated_market_maker_subaccounts' => true,
                'canonical_ledger_capital_checks' => true,
                'maker_checker_activation' => true,
                'mass_cancel' => true,
                'rebate_ledger_settlement' => true,
            ],
        ]]);
    }

    public function applications(Request $request): JsonResponse
    {
        return response()->json(['data' => MarketMakerProgramApplication::query()->with(['institution', 'subaccount'])->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function transition(Request $request, string $uuid): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string'], 'reason' => ['required', 'string', 'max:1000']]);
        $application = MarketMakerProgramApplication::query()->where('application_uuid', $uuid)->firstOrFail();

        return $this->handle(fn () => $this->program->transition($this->admin($request), $application, $payload['status'], $payload['reason'], $request));
    }

    public function activate(Request $request, string $uuid): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $application = MarketMakerProgramApplication::query()->where('application_uuid', $uuid)->firstOrFail();

        return $this->handle(fn () => $this->program->activate($this->admin($request), $application, $payload['reason'], $request), 201);
    }

    public function assign(Request $request, string $profileUuid): JsonResponse
    {
        $payload = $request->validate([
            'market_symbol' => ['required', 'string', 'max:48'],
            'minimum_depth' => ['nullable', 'numeric', 'gte:0'],
            'maximum_spread_bps' => ['nullable', 'numeric', 'gte:0'],
            'minimum_quote_presence' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'target_quote_size' => ['nullable', 'numeric', 'gte:0'],
            'maximum_inventory' => ['nullable', 'numeric', 'gte:0'],
            'rebate_profile' => ['nullable', 'array'],
            'listing_liquidity_requirement_id' => ['nullable', 'integer'],
            'obligations' => ['nullable', 'array'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return $this->handle(fn () => $this->program->assignMarket($this->admin($request), $profile, $payload, $request), 201);
    }

    public function agreement(Request $request, string $profileUuid): JsonResponse
    {
        $payload = $request->validate([
            'market_symbol' => ['required', 'string', 'max:48'],
            'agreement_type' => ['nullable', 'string', 'max:40'],
            'base_commitment' => ['nullable', 'numeric', 'gte:0'],
            'quote_commitment' => ['nullable', 'numeric', 'gte:0'],
            'spread_requirement_bps' => ['nullable', 'numeric', 'gte:0'],
            'depth_requirement' => ['nullable', 'numeric', 'gte:0'],
            'quote_presence_requirement' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'rebate_profile' => ['nullable', 'array'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return $this->handle(fn () => $this->program->createAgreement($this->admin($request), $profile, $payload, $request), 201);
    }

    public function capital(string $profileUuid, string $symbol): JsonResponse
    {
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return response()->json(['data' => $this->program->capitalReadiness($profile, $symbol)]);
    }

    public function listingReadiness(string $symbol): JsonResponse
    {
        return response()->json(['data' => $this->program->listingReadiness($symbol)]);
    }

    public function inventory(string $profileUuid, string $symbol): JsonResponse
    {
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return response()->json(['data' => $this->inventory->snapshot($profile, $symbol)], 201);
    }

    public function health(string $symbol): JsonResponse
    {
        return response()->json(['data' => $this->health->snapshot($symbol)], 201);
    }

    public function setSafetyMode(Request $request, string $profileUuid): JsonResponse
    {
        $payload = $request->validate(['mode' => ['required', 'string'], 'reason' => ['required', 'string', 'max:1000']]);
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return $this->handle(fn () => $this->program->setSafetyMode($this->admin($request), $profile, $payload['mode'], $payload['reason'], $request));
    }

    public function accrueRebate(Request $request, string $profileUuid): JsonResponse
    {
        $payload = $request->validate([
            'assignment_id' => ['nullable', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'eligible_maker_volume' => ['required', 'numeric', 'gte:0'],
            'rebate_bps' => ['required', 'numeric', 'gte:0'],
            'rebate_asset' => ['required', 'string', 'max:24'],
        ]);
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();
        $assignment = isset($payload['assignment_id']) ? MarketMakerMarketAssignment::query()->where('market_maker_id', $profile->id)->findOrFail($payload['assignment_id']) : null;

        return $this->handle(fn () => $this->rebates->accrue($profile, $assignment, $payload['period_start'], $payload['period_end'], (string) $payload['eligible_maker_volume'], (string) $payload['rebate_bps'], strtoupper($payload['rebate_asset'])), 201);
    }

    public function payRebate(Request $request, string $rebateUuid): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $period = MarketMakerRebatePeriod::query()->where('rebate_uuid', $rebateUuid)->firstOrFail();

        return $this->handle(fn () => $this->rebates->pay($this->admin($request), $period, $payload['reason']));
    }

    public function surveillanceOverlap(string $profileUuid, string $symbol): JsonResponse
    {
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return response()->json(['data' => $this->surveillance->detectRelatedInstitutionMarketOverlap($profile, $symbol)]);
    }

    public function massCancel(Request $request, string $profileUuid): JsonResponse
    {
        $payload = $request->validate(['market_symbol' => ['nullable', 'string', 'max:48'], 'reason' => ['required', 'string', 'max:1000']]);
        $profile = MarketMakerProfile::query()->where('profile_uuid', $profileUuid)->firstOrFail();

        return response()->json(['data' => $this->massCancel->cancelQuotes($this->admin($request), $profile, $payload['market_symbol'] ?? null, $payload['reason'])]);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin || ! $admin->hasPermission('liquidity.manage')) {
            throw new RuntimeException('Liquidity admin permission is required.');
        }

        return $admin;
    }

    private function handle(\Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
