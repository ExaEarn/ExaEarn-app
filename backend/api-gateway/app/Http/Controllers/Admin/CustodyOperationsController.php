<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Custody\BlockchainNetworkHealthService;
use App\Services\Custody\CustodyOperationalReadinessService;
use App\Services\Custody\CustodyReconciliationService;
use App\Services\Custody\CustodyRegistryService;
use App\Services\Custody\CustodyWithdrawalService;
use App\Services\Custody\DepositSweepService;
use App\Services\Custody\NetworkFeeManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustodyOperationsController extends Controller
{
    public function overview(CustodyOperationalReadinessService $readiness, CustodyReconciliationService $reconciliation): JsonResponse
    {
        return response()->json([
            'data' => [
                'readiness' => $readiness->evaluate(),
                'latest_reconciliation' => DB::table('custody_reconciliation_runs')->latest()->first() ?: $reconciliation->run(),
                'pending_deposits' => DB::table('custody_deposits')->whereNotIn('status', ['CREDITED', 'REJECTED', 'REVERSED'])->count(),
                'pending_withdrawals' => DB::table('custody_withdrawals')->whereNotIn('status', ['COMPLETED', 'FAILED', 'CANCELLED'])->count(),
            ],
        ]);
    }

    public function networks(CustodyRegistryService $registry, BlockchainNetworkHealthService $health): JsonResponse
    {
        $registry->syncFromConfig();
        $health->refresh();

        return response()->json(['data' => DB::table('blockchain_networks')->orderBy('network')->get()]);
    }

    public function wallets(): JsonResponse
    {
        return response()->json(['data' => DB::table('custody_wallets')->orderBy('network')->orderBy('classification')->get()]);
    }

    public function deposits(): JsonResponse
    {
        return response()->json(['data' => DB::table('custody_deposits')->latest()->limit(100)->get()]);
    }

    public function withdrawals(): JsonResponse
    {
        return response()->json(['data' => DB::table('custody_withdrawals')->latest()->limit(100)->get()]);
    }

    public function reconciliation(Request $request, CustodyReconciliationService $reconciliation): JsonResponse
    {
        return response()->json(['data' => $reconciliation->run($request->query('asset'), $request->query('network'))]);
    }

    public function hotWallets(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('custody_wallets')->where('classification', 'HOT')->orderBy('network')->get(),
        ]);
    }

    public function withdrawalReserves(): JsonResponse
    {
        return response()->json(['data' => DB::table('withdrawal_liquidity_reserves')->latest()->limit(100)->get()]);
    }

    public function networkFees(NetworkFeeManagementService $fees): JsonResponse
    {
        return response()->json(['data' => $fees->status()]);
    }

    public function signers(): JsonResponse
    {
        return response()->json([
            'data' => [
                'provider' => config('custody.signing.provider'),
                'production_enabled' => config('custody.production_enabled'),
                'development_signer_blocked_in_production' => true,
                'recent_requests' => DB::table('custody_signing_requests')->latest()->limit(50)->get(['signing_request_id', 'provider', 'network', 'status', 'created_at']),
            ],
        ]);
    }

    public function approveWithdrawal(Request $request, CustodyWithdrawalService $withdrawals, string $withdrawalId): JsonResponse
    {
        return response()->json([
            'data' => $withdrawals->approve($withdrawalId, $request->user()?->id),
        ]);
    }

    public function runSweep(Request $request, DepositSweepService $sweeps): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string'],
            'network' => ['required', 'string'],
            'address_balance' => ['required', 'numeric'],
        ]);

        return response()->json([
            'data' => $sweeps->evaluate($validated['asset'], $validated['network'], (string) $validated['address_balance']),
        ]);
    }
}
