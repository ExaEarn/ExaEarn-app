<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UnifiedTradingAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AccountController extends Controller
{
    public function index(Request $request, UnifiedTradingAccountService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $service->getAccountsOverview((int) $request->user()->id),
        ]);
    }

    public function funding(Request $request, UnifiedTradingAccountService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $service->getFundingSummary((int) $request->user()->id),
        ]);
    }

    public function unifiedTrading(Request $request, UnifiedTradingAccountService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $service->getUnifiedTradingSummary((int) $request->user()->id),
        ]);
    }

    public function unifiedTradingBalances(Request $request, UnifiedTradingAccountService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => array_values($service->getUnifiedTradingBalances((int) $request->user()->id)),
        ]);
    }

    public function transfer(Request $request, UnifiedTradingAccountService $service): JsonResponse
    {
        $validated = $request->validate([
            'from_account' => 'required|string|in:funding,unified_trading',
            'to_account' => 'required|string|in:funding,unified_trading',
            'asset' => 'required|string|max:32',
            'amount' => 'required|numeric|min:0.00000001',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        try {
            $transfer = $service->transfer(
                (int) $request->user()->id,
                (string) $validated['from_account'],
                (string) $validated['to_account'],
                (string) $validated['asset'],
                (string) $validated['amount'],
                isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Transfer completed.',
            'data' => $transfer,
        ]);
    }

    public function transferHistory(Request $request, UnifiedTradingAccountService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $service->getTransfers((int) $request->user()->id),
        ]);
    }
}
