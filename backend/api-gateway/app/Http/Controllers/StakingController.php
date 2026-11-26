<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StakingController extends Controller
{
    public function __construct(private readonly StakingService $stakingService) {}

    public function assets(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->listAssets()]);
    }

    public function products(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->listProducts()]);
    }

    public function product(string $slug): JsonResponse
    {
        try {
            return response()->json(['data' => $this->stakingService->productBySlug($slug)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }

    public function portfolio(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->userPortfolio((int) $request->user()->id)]);
    }

    public function positions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->userPositions((int) $request->user()->id)]);
    }

    public function createPosition(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'staking_product_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'auto_compound' => ['nullable', 'boolean'],
            'terms_version' => ['required', 'string', 'max:32'],
            'transaction_pin' => ['nullable', 'string', 'max:120'],
            'two_factor_code' => ['nullable', 'string', 'max:20'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $userId = $request->user()?->id;
        if (! $userId && ! app()->environment('testing')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $position = $this->stakingService->createPosition((int) ($userId ?? 0), $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $position], 201);
    }

    public function showPosition(Request $request, string $publicId): JsonResponse
    {
        $positions = collect($this->stakingService->userPositions((int) $request->user()->id));
        $position = $positions->firstWhere('public_id', $publicId);

        if (! $position) {
            return response()->json(['message' => 'Staking position not found.'], 404);
        }

        return response()->json(['data' => $position]);
    }

    public function unstake(Request $request, string $publicId): JsonResponse
    {
        $payload = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'transaction_pin' => ['nullable', 'string', 'max:120'],
            'two_factor_code' => ['nullable', 'string', 'max:20'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        try {
            return response()->json(['data' => $this->stakingService->requestUnstake((int) $request->user()->id, $publicId, $payload)], 202);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function acceptTerms(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'terms_version' => ['required', 'string', 'max:32'],
        ]);

        $this->stakingService->acceptTerms((int) $request->user()->id, $payload['terms_version'], [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['status' => 'accepted']);
    }

    public function terms(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->terms()]);
    }

    public function claimNativeRewards(Request $request, string $publicId): JsonResponse
    {
        try {
            return response()->json(['data' => $this->stakingService->claimNativeRewards((int) $request->user()->id, $publicId)], 202);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function claimExaTokenRewards(Request $request, string $publicId): JsonResponse
    {
        try {
            return response()->json(['data' => $this->stakingService->claimExaTokenRewards((int) $request->user()->id, $publicId)], 202);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function updateAutoCompound(Request $request, string $publicId): JsonResponse
    {
        $payload = $request->validate(['auto_compound' => ['required', 'boolean']]);

        try {
            return response()->json(['data' => $this->stakingService->updateAutoCompound((int) $request->user()->id, $publicId, (bool) $payload['auto_compound'])]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function rewards(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->userRewards((int) $request->user()->id)]);
    }

    public function transactions(Request $request): JsonResponse
    {
        if ($request->boolean('cursor_mode')) {
            $filters = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'cursor' => ['nullable', 'string', 'max:512'],
            ]);
            $page = $this->stakingService->userTransactionsCursor(
                (int) $request->user()->id,
                (int) ($filters['per_page'] ?? 30),
                $filters['cursor'] ?? null,
            );
            $items = array_map(static fn (object $transaction): array => [
                'transaction_id' => $transaction->public_id,
                'type' => $transaction->transaction_type,
                'amount' => (string) $transaction->amount,
                'fee' => (string) $transaction->fee_amount,
                'net_amount' => (string) $transaction->net_amount,
                'currency' => $transaction->symbol,
                'network' => $transaction->network,
                'precision' => $transaction->precision,
                'status' => $transaction->status,
                'tx_hash' => $transaction->blockchain_transaction_hash,
                'created_at' => $transaction->created_at,
                'processed_at' => $transaction->processed_at,
            ], $page->items());
            return response()->json([
                'data' => $items,
                'meta' => [
                    'next_cursor' => $page->nextCursor()?->encode(),
                    'has_more' => $page->hasMorePages(),
                    'per_page' => $page->perPage(),
                ],
            ]);
        }

        return response()->json(['data' => $this->stakingService->userTransactions((int) $request->user()->id)]);
    }

    public function apyHistory(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->apyHistory()]);
    }

    public function exaTokenCampaigns(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->exaTokenCampaigns()]);
    }

    public function networkStatuses(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->networkStatuses()]);
    }

    public function unbondingEstimates(): JsonResponse
    {
        return response()->json(['data' => $this->stakingService->unbondingEstimates()]);
    }

    public function unavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Legacy XRP/paper staking has been removed. Use /api/v1/staking for ExaEarn Native PoS Staking.',
        ], 410);
    }
}
