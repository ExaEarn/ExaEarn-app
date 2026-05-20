<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FeeTreasuryService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly FeeTreasuryService $feeTreasuryService,
    )
    {
    }

    public function createTransaction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        try {
            $tx = $this->ledgerService->createTransaction($payload['reference'], $payload['description']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $tx], 201);
    }

    public function addEntry(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric'],
            'asset' => ['required', 'string', 'max:16'],
            'reference' => ['required', 'string', 'max:100'],
            'transaction_type' => ['required', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $entry = $this->ledgerService->addEntry(
                (int) $payload['account_id'],
                (string) $payload['amount'],
                $payload['asset'],
                $payload['reference'],
                $payload['transaction_type'],
                $payload['user_id'] ?? null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $entry], 201);
    }

    public function commit(Request $request): JsonResponse
    {
        $payload = $request->validate(['reference' => ['required', 'string', 'max:100']]);

        try {
            $tx = $this->ledgerService->commitTransaction($payload['reference']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $tx]);
    }

    public function rollback(Request $request): JsonResponse
    {
        $payload = $request->validate(['reference' => ['required', 'string', 'max:100']]);

        try {
            $tx = $this->ledgerService->rollbackTransaction($payload['reference']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $tx]);
    }

    public function operation(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'operation' => ['required', 'string', 'in:fiat_deposit,crypto_deposit,internal_transfer,withdrawal,fee,exapoint_reward,trade'],
            'reference' => ['required', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'amount' => ['nullable', 'numeric'],
            'asset' => ['nullable', 'string', 'max:16'],
            'from_account_type' => ['nullable', 'string', 'max:32'],
            'to_account_type' => ['nullable', 'string', 'max:32'],
            'legs' => ['nullable', 'array'],
        ]);

        try {
            $tx = match ($payload['operation']) {
                'fiat_deposit' => $this->ledgerService->fiatDeposit((int) $payload['user_id'], (string) $payload['amount'], (string) $payload['asset'], $payload['reference']),
                'crypto_deposit' => $this->ledgerService->cryptoDeposit((int) $payload['user_id'], (string) $payload['amount'], (string) $payload['asset'], $payload['reference']),
                'internal_transfer' => $this->ledgerService->internalTransfer((int) $payload['user_id'], (string) $payload['from_account_type'], (string) $payload['to_account_type'], (string) $payload['amount'], (string) $payload['asset'], $payload['reference']),
                'withdrawal' => $this->ledgerService->withdrawal((int) $payload['user_id'], (string) $payload['amount'], (string) $payload['asset'], $payload['reference']),
                'fee' => $this->ledgerService->chargeFee((int) $payload['user_id'], (string) $payload['amount'], (string) $payload['asset'], $payload['reference']),
                'exapoint_reward' => $this->ledgerService->exapointReward((int) $payload['user_id'], (string) $payload['amount'], $payload['reference']),
                'trade' => $this->ledgerService->trade((array) ($payload['legs'] ?? []), $payload['reference']),
            };
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $tx], 201);
    }

    public function feeOperation(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'operation' => ['required', 'string', 'in:withdrawal,spot,futures,fiat_deposit'],
            'reference' => ['required', 'string', 'max:100'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'asset' => ['nullable', 'string', 'max:16'],
            'liquidity_role' => ['nullable', 'string', 'in:maker,taker'],
            'account_type' => ['nullable', 'string', 'max:32'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $result = match ($payload['operation']) {
                'withdrawal' => $this->feeTreasuryService->collectWithdrawal(
                    (int) $payload['user_id'],
                    (string) $payload['amount'],
                    (string) ($payload['asset'] ?? 'USDT'),
                    (string) $payload['reference'],
                    $payload['metadata'] ?? []
                ),
                'spot' => $this->feeTreasuryService->collectSpot(
                    (int) $payload['user_id'],
                    (string) $payload['amount'],
                    (string) ($payload['asset'] ?? 'USDT'),
                    (string) $payload['reference'],
                    (string) ($payload['liquidity_role'] ?? 'taker'),
                    (string) ($payload['account_type'] ?? 'spot'),
                    $payload['metadata'] ?? []
                ),
                'futures' => $this->feeTreasuryService->collectFutures(
                    (int) $payload['user_id'],
                    (string) $payload['amount'],
                    (string) $payload['reference'],
                    (string) ($payload['liquidity_role'] ?? 'taker'),
                    $payload['metadata'] ?? []
                ),
                'fiat_deposit' => $this->feeTreasuryService->collectFiatDeposit(
                    (int) $payload['user_id'],
                    (string) $payload['amount'],
                    (string) ($payload['asset'] ?? 'NGN'),
                    (string) $payload['reference'],
                    $payload['metadata'] ?? []
                ),
            };
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }
}
