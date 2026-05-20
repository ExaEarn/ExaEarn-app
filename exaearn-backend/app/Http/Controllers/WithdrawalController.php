<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\FiatWithdrawalService;
use App\Services\TransactionGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly FiatWithdrawalService $withdrawalService,
        private readonly TransactionGuardService $transactionGuardService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'currency' => ['required', 'string', 'in:NGN,ZAR,USD'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'destination_account' => ['required', 'array'],
            'destination_account.account_number' => ['required', 'string'],
            'destination_account.account_name' => ['required', 'string'],
            'destination_account.bank_code' => ['required', 'string'],
            'destination_account.bank_name' => ['required', 'string'],
        ]);

        try {
            $guard = $this->transactionGuardService->guardWithdrawal(
                $request->user(),
                (string) $payload['amount']
            );

            $withdrawal = $this->withdrawalService->initiateWithdrawal(
                $request->user()->id,
                $payload['currency'],
                (string) $payload['amount'],
                $payload['destination_account']
            );

            $this->auditLogService->log($request->user()->id, 'withdrawal.requested', $request, [
                'currency' => $payload['currency'],
                'amount' => (string) $payload['amount'],
                'risk' => $guard['risk'] ?? null,
                'delay_seconds' => $guard['delay_seconds'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Withdrawal initiated successfully.',
            'data' => $withdrawal,
        ], 201);
    }

    public function status(Request $request, string $reference): JsonResponse
    {
        $withdrawal = $this->withdrawalService->getWithdrawalStatus($reference);

        if (!$withdrawal) {
            return response()->json(['message' => 'Withdrawal not found.'], 404);
        }

        return response()->json([
            'message' => 'Withdrawal status retrieved.',
            'data' => $withdrawal,
        ]);
    }

    public function cancel(Request $request, string $reference): JsonResponse
    {
        try {
            $withdrawal = $this->withdrawalService->cancelWithdrawal($reference, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Withdrawal cancelled successfully.',
            'data' => $withdrawal,
        ]);
    }
}
