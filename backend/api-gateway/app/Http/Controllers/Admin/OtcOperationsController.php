<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\OtcAuditLog;
use App\Models\OtcLiquidityProvider;
use App\Models\OtcMarketConfig;
use App\Models\OtcQuote;
use App\Models\OtcRfq;
use App\Models\OtcRiskEvent;
use App\Models\OtcSettlement;
use App\Models\OtcTrade;
use App\Services\OtcRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OtcOperationsController extends Controller
{
    public function __construct(private readonly OtcRfqService $otc)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'enabled_markets' => OtcMarketConfig::query()->where('enabled', true)->count(),
            'active_providers' => OtcLiquidityProvider::query()->where('status', 'ACTIVE')->count(),
            'open_rfqs' => OtcRfq::query()->whereIn('status', ['REQUESTED', 'QUOTING', 'QUOTED', 'APPROVAL_REQUIRED', 'MANUAL_REVIEW'])->count(),
            'settled_trades' => OtcTrade::query()->where('status', 'SETTLED')->count(),
            'pending_settlements' => OtcSettlement::query()->whereIn('status', ['PENDING', 'RESERVED', 'EXECUTED', 'SETTLING'])->count(),
            'open_risk_events' => OtcRiskEvent::query()->where('status', 'OPEN')->count(),
            'public_market_data_isolated' => true,
        ]]);
    }

    public function upsertMarket(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'symbol' => ['required', 'string', 'max:48'],
            'product_type' => ['nullable', 'string', 'max:40'],
            'base_asset' => ['required', 'string', 'max:24'],
            'quote_asset' => ['required', 'string', 'max:24'],
            'enabled' => ['nullable', 'boolean'],
            'minimum_size' => ['nullable', 'numeric', 'gte:0'],
            'maximum_size' => ['nullable', 'numeric', 'gte:0'],
            'quote_ttl_seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'allowed_account_types' => ['nullable', 'array'],
            'allowed_jurisdictions' => ['nullable', 'array'],
            'eligible_liquidity_sources' => ['nullable', 'array'],
            'max_spread_bps' => ['nullable', 'numeric', 'gte:0'],
            'manual_review_threshold' => ['nullable', 'numeric', 'gte:0'],
            'settlement_mode' => ['nullable', 'string', 'max:40'],
            'partial_fill_policy' => ['nullable', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        return $this->handle(fn () => $this->otc->createMarketConfig($this->admin($request), $payload), 201);
    }

    public function registerProvider(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'provider_type' => ['required', 'string', 'max:40'],
            'market_maker_id' => ['nullable', 'integer'],
            'institution_id' => ['nullable', 'integer'],
            'subaccount_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:40'],
            'capabilities' => ['nullable', 'array'],
            'markets' => ['required', 'array', 'min:1'],
            'limits' => ['nullable', 'array'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        return $this->handle(fn () => $this->otc->registerProvider($this->admin($request), $payload), 201);
    }

    public function submitQuote(Request $request, string $rfqUuid, string $providerUuid): JsonResponse
    {
        $payload = $request->validate([
            'price' => ['required', 'numeric', 'gt:0'],
            'available_base_amount' => ['required', 'numeric', 'gt:0'],
            'minimum_base_amount' => ['nullable', 'numeric', 'gte:0'],
            'client_fee' => ['nullable', 'numeric', 'gte:0'],
            'ttl_seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'provider_reference' => ['nullable', 'string', 'max:160'],
        ]);
        $rfq = OtcRfq::query()->where('rfq_uuid', $rfqUuid)->firstOrFail();
        $provider = OtcLiquidityProvider::query()->where('provider_uuid', $providerUuid)->firstOrFail();
        return $this->handle(fn () => $this->otc->submitProviderQuote($rfq, $provider, $payload), 201);
    }

    public function rfqs(Request $request): JsonResponse
    {
        return response()->json(['data' => OtcRfq::query()->with('quotes')->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function quotes(Request $request): JsonResponse
    {
        return response()->json(['data' => OtcQuote::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function trades(Request $request): JsonResponse
    {
        return response()->json(['data' => OtcTrade::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function reconcile(): JsonResponse
    {
        return response()->json(['data' => $this->otc->reconcile()], 201);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(['data' => OtcAuditLog::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin || ! $admin->hasPermission('institutional.manage')) {
            throw new RuntimeException('Institutional admin permission is required.');
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
