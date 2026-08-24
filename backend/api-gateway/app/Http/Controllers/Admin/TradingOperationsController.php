<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CollateralConfiguration;
use App\Models\CollateralConfigurationVersion;
use App\Models\InsuranceFundAccount;
use App\Models\MarginLendingPool;
use App\Models\Market;
use App\Models\TradingCircuitBreaker;
use App\Models\TradingIncident;
use App\Services\CircuitBreakerService;
use App\Services\ExchangeOperationalReadinessService;
use App\Services\FinancialReconciliationService;
use App\Services\InsuranceFundService;
use App\Services\LendingPoolRiskService;
use App\Services\TradingLoadProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TradingOperationsController extends Controller
{
    public function __construct(
        private readonly ExchangeOperationalReadinessService $readiness,
        private readonly FinancialReconciliationService $reconciliation,
        private readonly CircuitBreakerService $breakers,
        private readonly LendingPoolRiskService $lending,
        private readonly InsuranceFundService $insurance,
        private readonly TradingLoadProbeService $loadProbe,
    ) {
    }

    public function readiness(): JsonResponse
    {
        return response()->json(['data' => $this->readiness->evaluate()]);
    }

    public function reconciliation(): JsonResponse
    {
        $run = $this->reconciliation->run();

        return response()->json(['data' => $run->load('differences')]);
    }

    public function treasuryExposure(): JsonResponse
    {
        $liabilities = Account::query()
            ->whereNotNull('user_id')
            ->selectRaw('asset, sum(balance) as balance')
            ->groupBy('asset')
            ->get();
        $treasury = Account::query()
            ->whereNull('user_id')
            ->selectRaw('asset, account_type, sum(balance) as balance')
            ->groupBy('asset', 'account_type')
            ->get();

        return response()->json([
            'data' => [
                'user_liabilities' => $liabilities,
                'treasury_accounts' => $treasury,
                'lending_pools' => MarginLendingPool::query()->orderBy('asset')->get(),
                'lending_risk' => $this->lending->assess(),
                'insurance_funds' => InsuranceFundAccount::query()->orderBy('product')->orderBy('asset')->get(),
            ],
        ]);
    }

    public function transitionBreaker(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'string', 'max:32'],
            'scope_key' => ['nullable', 'string', 'max:96'],
            'state' => ['required', 'in:NORMAL,WARNING,RESTRICTED,CANCEL_ONLY,REDUCE_ONLY,PAUSED,EMERGENCY_STOP'],
            'reason' => ['required', 'string', 'max:1000'],
            'reason_code' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ]);

        $breaker = $this->breakers->transition(
            $data['scope'],
            $data['scope_key'] ?? '*',
            $data['state'],
            $data['reason'],
            $request->user()?->id,
            $data['reason_code'] ?? null,
            $data['metadata'] ?? [],
        );

        return response()->json(['data' => $breaker]);
    }

    public function pauseMarket(Request $request, string $symbol): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $symbol = $this->normalizeSymbol($symbol);
        Market::query()->where('symbol', $symbol)->update(['trading_status' => 'halted']);
        $breaker = $this->breakers->transition('MARKET', $symbol, TradingCircuitBreaker::PAUSED, $data['reason'], $request->user()?->id, 'ADMIN_MARKET_PAUSE', ['product' => 'spot']);

        return response()->json(['data' => $breaker]);
    }

    public function resumeMarket(Request $request, string $symbol): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $symbol = $this->normalizeSymbol($symbol);
        Market::query()->where('symbol', $symbol)->update(['trading_status' => 'trading']);
        $breaker = $this->breakers->transition('MARKET', $symbol, TradingCircuitBreaker::NORMAL, $data['reason'], $request->user()?->id, 'ADMIN_MARKET_RESUME', ['product' => 'spot']);

        return response()->json(['data' => $breaker]);
    }

    public function killSwitch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'incident_id' => ['nullable', 'string', 'max:96'],
        ]);

        $breaker = $this->breakers->transition('GLOBAL', '*', TradingCircuitBreaker::EMERGENCY_STOP, $data['reason'], $request->user()?->id, 'ADMIN_GLOBAL_KILL_SWITCH', [
            'incident_id' => $data['incident_id'] ?? null,
        ]);

        return response()->json(['data' => $breaker]);
    }

    public function updateCollateral(Request $request, string $asset): JsonResponse
    {
        $data = $request->validate([
            'collateral_factor' => ['required', 'numeric', 'min:0', 'max:1'],
            'max_collateral_amount' => ['nullable', 'numeric', 'min:0'],
            'concentration_threshold_bps' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'volatility_category' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:ACTIVE,DISABLED'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $asset = strtoupper($asset);
        $config = CollateralConfiguration::query()->where('asset', $asset)->first();
        $before = $config?->toArray();
        $version = (int) ($config?->version ?? 0) + 1;
        $config = CollateralConfiguration::query()->updateOrCreate(['asset' => $asset], [
            'collateral_factor' => (string) $data['collateral_factor'],
            'max_collateral_amount' => isset($data['max_collateral_amount']) ? (string) $data['max_collateral_amount'] : null,
            'concentration_threshold_bps' => isset($data['concentration_threshold_bps']) ? (string) $data['concentration_threshold_bps'] : null,
            'volatility_category' => $data['volatility_category'] ?? 'STANDARD',
            'status' => $data['status'] ?? 'ACTIVE',
            'version' => $version,
            'effective_at' => now(),
        ]);
        CollateralConfigurationVersion::query()->create([
            'collateral_configuration_id' => $config->id,
            'version' => $version,
            'before_state' => $before,
            'after_state' => $config->toArray(),
            'changed_by_admin_id' => $request->user()?->id,
            'reason' => $data['reason'],
            'changed_at' => now(),
        ]);

        return response()->json(['data' => $config]);
    }

    public function insuranceCredit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:32'],
            'asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'reference' => ['nullable', 'string', 'max:96'],
        ]);

        return response()->json([
            'data' => $this->insurance->credit($data['product'], $data['asset'], (string) $data['amount'], $data['reference'] ?? 'admin-insurance-credit:' . Str::uuid(), [
                'admin_id' => $request->user()?->id,
            ]),
        ]);
    }

    public function insuranceUse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:32'],
            'asset' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'reference' => ['nullable', 'string', 'max:96'],
        ]);

        return response()->json([
            'data' => $this->insurance->useFund($data['product'], $data['asset'], (string) $data['amount'], $data['reference'] ?? 'admin-insurance-use:' . Str::uuid(), [
                'admin_id' => $request->user()?->id,
            ]),
        ]);
    }

    public function loadProbe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'string', 'max:64'],
            'iterations' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        return response()->json(['data' => $this->loadProbe->run($data['scope'] ?? 'risk_engine', (int) ($data['iterations'] ?? 0))]);
    }

    public function incidents(): JsonResponse
    {
        return response()->json(['data' => TradingIncident::query()->with('events')->latest()->paginate(50)]);
    }

    private function normalizeSymbol(string $symbol): string
    {
        $symbol = strtoupper($symbol);
        if (str_contains($symbol, '/')) {
            return $symbol;
        }

        foreach (['USDT', 'USDC', 'USD', 'BTC', 'ETH'] as $quote) {
            if (str_ends_with($symbol, $quote) && strlen($symbol) > strlen($quote)) {
                return substr($symbol, 0, -strlen($quote)) . '/' . $quote;
            }
        }

        return $symbol;
    }
}
