<?php

namespace App\Http\Controllers;

use App\Services\FiatWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FiatWithdrawalController
 *
 * Handles fiat withdrawal operations, bank lookups, and status checks.
 */
class FiatWithdrawalController extends Controller
{
    public function __construct(
        private readonly FiatWithdrawalService $fiatService,
    ) {
    }

    /**
     * GET /api/fiat/banks
     * Get list of supported Nigerian banks for withdrawals.
     */
    public function supportedBanks(): JsonResponse
    {
        $banks = $this->fiatService->getSupportedBanks();

        return response()->json([
            'data' => $banks,
            'count' => count($banks),
        ]);
    }

    /**
     * POST /api/fiat-withdrawals/initiate
     * Initiate a fiat withdrawal to a bank account.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:100|max:5000000',
            'currency' => 'required|string|in:NGN,USD',
            'bank_code' => 'required|string',
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string|max:100',
            'narration' => 'nullable|string|max:255',
        ]);

        try {
            $result = $this->fiatService->initiateFiatWithdrawal(
                $request->user(),
                (string) $request->input('amount'),
                (string) $request->input('currency'),
                (string) $request->input('bank_code'),
                (string) $request->input('account_number'),
                (string) $request->input('account_name'),
                $request->input('narration'),
            );

            return response()->json([
                'message' => 'Fiat withdrawal initiated successfully.',
                'data' => [
                    'withdrawal_id' => $result['withdrawal']->id ?? null,
                    'reference' => $result['reference_id'] ?? null,
                    'gateway_reference' => $result['reference_id'] ?? null,
                    'status' => $result['status'] ?? 'processing',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initiate fiat withdrawal.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/fiat/withdrawal/{withdrawalId}/status
     * Check the current status of a fiat withdrawal.
     */
    public function withdrawalStatus(Request $request, int $withdrawalId): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::where('user_id', $request->user()->id)
            ->where('id', $withdrawalId)
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'message' => 'Withdrawal not found.',
            ], 404);
        }

        // If it's a crypto withdrawal, return as-is
        if ($withdrawal->network) {
            return response()->json([
                'data' => [
                    'id' => $withdrawal->id,
                    'status' => $withdrawal->status,
                    'amount' => $withdrawal->amount,
                    'currency' => $withdrawal->currency,
                    'network' => $withdrawal->network,
                    'address' => $withdrawal->address,
                    'tx_hash' => $withdrawal->tx_hash,
                    'created_at' => $withdrawal->created_at->toISOString(),
                    'confirmed_at' => $withdrawal->confirmed_at?->toISOString(),
                ],
            ]);
        }

        // For fiat withdrawals, check with gateway
        $statusResult = $this->fiatService->checkWithdrawalStatus($withdrawal);

        return response()->json([
            'data' => [
                'id' => $withdrawal->id,
                'status' => $withdrawal->status,
                'amount' => $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'created_at' => $withdrawal->created_at->toISOString(),
                'confirmed_at' => $withdrawal->confirmed_at?->toISOString(),
                'gateway' => $withdrawal->metadata['gateway'] ?? null,
                'gateway_reference' => $withdrawal->metadata['gateway_reference_id'] ?? null,
                'gateway_status' => $statusResult['gateway_status'] ?? null,
            ],
        ]);
    }
}
