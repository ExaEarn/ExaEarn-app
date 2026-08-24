<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
use App\Models\ListingApplication;
use App\Models\ListingAuditLog;
use App\Models\ListingLaunchSchedule;
use App\Models\ListingContractValidation;
use App\Models\ListingTestRun;
use App\Models\ListingTokenMigration;
use App\Models\Market;
use App\Services\ListingLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ListingCenterController extends Controller
{
    public function __construct(private readonly ListingLifecycleService $listings)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'applications' => ListingApplication::query()->count(),
            'submitted' => ListingApplication::query()->where('application_status', 'SUBMITTED')->count(),
            'approved' => ListingApplication::query()->where('application_status', 'APPROVED')->count(),
            'integration' => ListingApplication::query()->whereIn('integration_status', ['INTEGRATION', 'ASSET_CONFIGURATION', 'MARKET_CREATED', 'TESTING', 'READY_FOR_LISTING'])->count(),
            'scheduled' => ListingApplication::query()->where('integration_status', 'SCHEDULED')->count(),
            'live' => ListingApplication::query()->where('integration_status', 'LIVE')->count(),
            'networks' => BlockchainNetwork::query()->count(),
            'registered_assets' => BlockchainAsset::query()->count(),
            'markets' => Market::query()->count(),
        ]]);
    }

    public function applications(Request $request): JsonResponse
    {
        $query = ListingApplication::query()->with(['organization', 'reviews', 'assetConfiguration', 'networkConfigurations', 'contractValidations', 'marketConfigurations', 'schedule'])->latest();
        if ($request->filled('status')) {
            $query->where('application_status', strtoupper((string) $request->query('status')));
        }
        if ($request->filled('integration_status')) {
            $query->where('integration_status', strtoupper((string) $request->query('integration_status')));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 25))]);
    }

    public function review(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'review_type' => ['required', 'string'],
            'status' => ['required', 'string', 'in:NOT_STARTED,IN_REVIEW,PASSED,PASSED_WITH_CONDITIONS,FAILED'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scorecard' => ['nullable', 'array'],
            'risk_flags' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->handle(fn () => $this->listings->completeReview($this->admin($request), $this->application($reference), $payload, $request), 201);
    }

    public function recommend(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->listings->recommendApproval($this->admin($request), $this->application($reference), $payload['reason'], $request));
    }

    public function approve(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->listings->approveApplication($this->admin($request), $this->application($reference), $payload['reason'], $request));
    }

    public function createAssetConfiguration(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'symbol' => ['required', 'string', 'max:24'],
            'slug' => ['nullable', 'string', 'max:120'],
            'asset_type' => ['nullable', 'string', 'max:40'],
            'network' => ['required', 'string', 'max:64'],
            'token_standard' => ['required', 'string', 'max:40'],
            'contract_address' => ['nullable', 'string', 'max:160'],
            'decimals' => ['required', 'integer', 'min:0', 'max:36'],
            'explorer_url' => ['nullable', 'url', 'max:255'],
            'supply_metadata' => ['nullable', 'array'],
            'upgradeable' => ['nullable', 'boolean'],
            'proxy' => ['nullable', 'boolean'],
            'pausable' => ['nullable', 'boolean'],
            'blacklist_capability' => ['nullable', 'boolean'],
            'transfer_restriction' => ['nullable', 'boolean'],
            'fee_on_transfer' => ['nullable', 'boolean'],
            'mintable' => ['nullable', 'boolean'],
            'freeze_authority' => ['nullable', 'boolean'],
            'owner_privileges' => ['nullable', 'boolean'],
            'unusual_behavior' => ['nullable', 'boolean'],
        ]);

        return $this->handle(fn () => $this->listings->createAssetConfiguration($this->admin($request), $this->application($reference), $payload, $request), 201);
    }

    public function createMarket(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'quote_asset' => ['required', 'string', 'max:24'],
            'base_asset' => ['nullable', 'string', 'max:24'],
            'tick_size' => ['nullable', 'numeric', 'gt:0'],
            'quantity_step' => ['nullable', 'numeric', 'gt:0'],
            'min_quantity' => ['nullable', 'numeric', 'gte:0'],
            'max_quantity' => ['nullable', 'numeric', 'gte:0'],
            'min_notional' => ['nullable', 'numeric', 'gte:0'],
            'maker_fee' => ['nullable', 'numeric', 'gte:0'],
            'taker_fee' => ['nullable', 'numeric', 'gte:0'],
            'manual_price' => ['prohibited'],
            'reference_launch_price' => ['nullable', 'numeric', 'gt:0'],
            'liquidity_arrangement' => ['nullable', 'string', 'max:64'],
            'required_base_liquidity' => ['nullable', 'numeric', 'gte:0'],
            'required_quote_liquidity' => ['nullable', 'numeric', 'gte:0'],
            'maximum_spread_bps' => ['nullable', 'numeric', 'gte:0'],
            'minimum_depth' => ['nullable', 'numeric', 'gte:0'],
            'liquidity_status' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->handle(fn () => $this->listings->createMarketConfiguration($this->admin($request), $this->application($reference), $payload, $request), 201);
    }

    public function createNetworkConfiguration(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'network' => ['required', 'string', 'max:64'],
            'token_standard' => ['required', 'string', 'max:40'],
            'contract_address' => ['nullable', 'string', 'max:180'],
            'decimals' => ['nullable', 'integer', 'min:0', 'max:36'],
            'minimum_deposit' => ['nullable', 'numeric', 'gte:0'],
            'minimum_withdrawal' => ['nullable', 'numeric', 'gte:0'],
            'maximum_withdrawal' => ['nullable', 'numeric', 'gte:0'],
            'withdrawal_fee' => ['nullable', 'numeric', 'gte:0'],
            'explorer_url' => ['nullable', 'url', 'max:255'],
            'upgradeable' => ['nullable', 'boolean'],
            'proxy' => ['nullable', 'boolean'],
            'pausable' => ['nullable', 'boolean'],
            'blacklist_capability' => ['nullable', 'boolean'],
            'transfer_restriction' => ['nullable', 'boolean'],
            'fee_on_transfer' => ['nullable', 'boolean'],
            'mintable' => ['nullable', 'boolean'],
            'freeze_authority' => ['nullable', 'boolean'],
            'owner_privileges' => ['nullable', 'boolean'],
            'unusual_behavior' => ['nullable', 'boolean'],
        ]);

        return $this->handle(fn () => $this->listings->addNetworkConfiguration($this->admin($request), $this->application($reference), $payload, $request), 201);
    }

    public function runTests(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->runListingTests($this->admin($request), $this->application($reference), $request), 202);
    }

    public function requestFinalApproval(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->listings->requestFinalApproval($this->admin($request), $this->application($reference), $payload['reason'], $request));
    }

    public function schedule(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'announcement_at' => ['nullable', 'date'],
            'deposit_open_at' => ['nullable', 'date'],
            'trading_open_at' => ['required', 'date'],
            'withdrawal_open_at' => ['nullable', 'date'],
            'announcement_metadata' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->handle(fn () => $this->listings->schedule($this->admin($request), $this->application($reference), $payload, $request), 201);
    }

    public function launch(Request $request, string $reference): JsonResponse
    {
        return $this->handle(fn () => $this->listings->launch($this->admin($request), $this->application($reference), $request), 202);
    }

    public function processDueLaunchEvents(Request $request): JsonResponse
    {
        return $this->handle(fn () => $this->listings->processDueLaunchEvents($this->admin($request), $request), 202);
    }

    public function tokenMigration(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'migration_type' => ['required', 'string', 'in:TOKEN_REBRAND,CONTRACT_MIGRATION,NETWORK_MIGRATION,TOKEN_SWAP'],
            'old_network' => ['nullable', 'string', 'max:64'],
            'old_contract_address' => ['nullable', 'string', 'max:180'],
            'new_network' => ['nullable', 'string', 'max:64'],
            'new_contract_address' => ['nullable', 'string', 'max:180'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'plan' => ['nullable', 'array'],
        ]);

        return $this->handle(fn () => $this->listings->createTokenMigration($this->admin($request), $this->application($reference), $payload, $request), 202);
    }

    public function emergencyControl(Request $request, string $reference): JsonResponse
    {
        $payload = $request->validate([
            'control' => ['required', 'string', 'in:PAUSE_DEPOSITS,PAUSE_WITHDRAWALS,PAUSE_TRADING,HALT_ALL'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->handle(fn () => $this->listings->emergencyControl($this->admin($request), $this->application($reference), $payload, $request));
    }

    public function liveAssets(): JsonResponse
    {
        return response()->json(['data' => ListingApplication::query()
            ->where('integration_status', 'LIVE')
            ->with(['assetConfiguration', 'marketConfigurations'])
            ->latest()
            ->get()]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingAuditLog::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function testRuns(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingTestRun::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function schedules(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingLaunchSchedule::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function contractValidations(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingContractValidation::query()->latest('checked_at')->paginate((int) $request->query('per_page', 50))]);
    }

    public function tokenMigrations(Request $request): JsonResponse
    {
        return response()->json(['data' => ListingTokenMigration::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    private function application(string $reference): ListingApplication
    {
        return ListingApplication::query()->where('reference', $reference)->with(['organization', 'assetConfiguration', 'networkConfigurations', 'contractValidations', 'marketConfigurations', 'schedule'])->firstOrFail();
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            throw new RuntimeException('Admin authentication is required.');
        }
        if (! $admin->hasPermission('listing.manage')) {
            throw new RuntimeException('Listing management permission is required.');
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
