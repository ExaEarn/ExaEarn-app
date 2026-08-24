<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Fiat\FiatOperationalReadinessService;
use App\Services\Fiat\FiatReconciliationService;
use App\Services\Fiat\FiatTreasuryService;
use App\Services\Fiat\FiatWithdrawalProcessingService;
use App\Services\Fiat\FiatWithdrawalReserveService;
use App\Services\Fiat\PaymentProviderHealthService;
use App\Services\Fiat\PaymentRefundService;
use App\Services\Fiat\ProviderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FiatOperationsController extends Controller
{
    public function overview(FiatOperationalReadinessService $readiness): JsonResponse
    {
        return response()->json([
            'data' => [
                'readiness' => $readiness->evaluate(),
                'currencies' => DB::table('fiat_currencies')->orderBy('code')->get(),
                'providers' => DB::table('payment_provider_accounts')->orderBy('provider')->get(),
                'pending_withdrawals' => DB::table('phase10_fiat_withdrawals')->whereIn('status', ['RESERVED', 'SUBMITTED', 'PROCESSING', 'UNKNOWN'])->count(),
                'manual_review_deposits' => DB::table('fiat_deposits')->where('status', 'MANUAL_REVIEW')->count(),
            ],
        ]);
    }

    public function providerHealth(PaymentProviderHealthService $health): JsonResponse
    {
        return response()->json(['data' => $health->refreshAll()]);
    }

    public function allocateTreasury(Request $request, FiatTreasuryService $treasury): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'max:8'],
            'bucket' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gte:0'],
        ]);

        return response()->json(['data' => $treasury->allocate($validated['currency'], $validated['bucket'], (string) $validated['amount'])]);
    }

    public function refreshReserve(Request $request, FiatWithdrawalReserveService $reserves): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'max:8'],
            'provider' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => $reserves->refresh($validated['currency'], $validated['provider'] ?? null)]);
    }

    public function reconciliation(Request $request, FiatReconciliationService $reconciliation): JsonResponse
    {
        return response()->json(['data' => $reconciliation->run($request->string('currency')->toString() ?: null)]);
    }

    public function recordProviderSettlement(Request $request, ProviderSettlementService $settlements): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:40'],
            'provider_settlement_id' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'max:8'],
            'gross_amount' => ['required', 'numeric', 'gte:0'],
            'fee_amount' => ['nullable', 'numeric', 'gte:0'],
            'destination_bank' => ['nullable', 'string', 'max:160'],
            'settlement_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => $settlements->record($validated)], 201);
    }

    public function completeWithdrawal(string $withdrawalId, FiatWithdrawalProcessingService $withdrawals): JsonResponse
    {
        return response()->json(['data' => $withdrawals->complete($withdrawalId)]);
    }

    public function failWithdrawal(Request $request, string $withdrawalId, FiatWithdrawalProcessingService $withdrawals): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:160']]);

        return response()->json(['data' => $withdrawals->failAndRelease($withdrawalId, $validated['reason'])]);
    }

    public function refund(Request $request, PaymentRefundService $refunds): JsonResponse
    {
        $validated = $request->validate([
            'original_reference' => ['required', 'string', 'max:160'],
            'currency' => ['required', 'string', 'max:8'],
            'reason' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['data' => $refunds->reverseLedgerReference($validated['original_reference'], $validated['currency'], $validated['reason'])]);
    }
}
