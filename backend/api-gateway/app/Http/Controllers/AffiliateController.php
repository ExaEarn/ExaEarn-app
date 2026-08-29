<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AffiliateCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AffiliateController extends Controller
{
    public function __construct(private readonly AffiliateCommissionService $affiliates)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->affiliates->overview($request->user())]);
    }

    public function referrals(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        return response()->json(['data' => $this->affiliates->referrals($request->user(), $perPage)]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        return response()->json(['data' => $this->affiliates->earnings($request->user(), $perPage)]);
    }

    public function tools(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->affiliates->tools($request->user())]);
    }

    public function payouts(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'gt:0'],
                'asset' => ['nullable', 'string', 'max:24'],
                'idempotency_key' => ['nullable', 'string', 'max:160'],
            ]);

            try {
                return response()->json(['data' => $this->affiliates->requestPayout(
                    $request->user(),
                    (string) $data['amount'],
                    strtoupper((string) ($data['asset'] ?? 'EXAPOINT')),
                    $data['idempotency_key'] ?? $request->header('Idempotency-Key'),
                )], 201);
            } catch (RuntimeException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        return response()->json(['data' => $this->affiliates->payouts($request->user())]);
    }
}
