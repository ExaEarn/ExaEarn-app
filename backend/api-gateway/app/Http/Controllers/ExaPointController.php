<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExaPointTransaction;
use App\Models\ExapointBalance;
use App\Services\ExaPointConversionService;
use App\Services\ExaPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExaPointController extends Controller
{
    public function __construct(
        private readonly ExaPointService $exaPointService,
        private readonly ExaPointConversionService $conversionService,
    ) {
    }

    public function balance(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->exaPointService->getBalance((int) $request->user()->id),
        ]);
    }

    public function totals(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->exaPointService->getTotalExaPoints((int) $request->user()->id),
        ]);
    }

    public function spend(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->exaPointService->spend(
                (int) $request->user()->id,
                (string) $payload['amount'],
                (string) $payload['reference'],
                isset($payload['description']) ? (string) $payload['description'] : null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function lock(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->exaPointService->lock(
                (int) $request->user()->id,
                (string) $payload['amount'],
                isset($payload['reference']) ? (string) $payload['reference'] : null,
                isset($payload['description']) ? (string) $payload['description'] : null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->exaPointService->unlock(
                (int) $request->user()->id,
                (string) $payload['amount'],
                isset($payload['reference']) ? (string) $payload['reference'] : null,
                isset($payload['description']) ? (string) $payload['description'] : null,
                $payload['metadata'] ?? []
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'type' => ['required', 'string', 'in:credit,debit'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'approved_large_adjustment' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->exaPointService->adjust(
                (int) $payload['user_id'],
                (string) $payload['amount'],
                (string) $payload['type'],
                $request->user(),
                isset($payload['reference']) ? (string) $payload['reference'] : null,
                isset($payload['description']) ? (string) $payload['description'] : null,
                $payload['metadata'] ?? [],
                (bool) ($payload['approved_large_adjustment'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function convert(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'points_to_convert' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            $record = $this->conversionService->convertPointsToToken(
                (int) $request->user()->id,
                isset($payload['rate']) ? (string) $payload['rate'] : null,
                isset($payload['points_to_convert']) ? (string) $payload['points_to_convert'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $record], 201);
    }

    public function adminSummary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_available_points' => (string) ExapointBalance::query()->sum('available_points'),
                'total_locked_points' => (string) ExapointBalance::query()->sum('locked_points'),
                'total_earned_points' => (string) ExapointBalance::query()->sum('total_earned'),
                'total_spent_points' => (string) ExapointBalance::query()->sum('total_spent'),
                'transaction_count' => ExaPointTransaction::query()->count(),
            ],
        ]);
    }

    public function adminUserHistory(int $userId): JsonResponse
    {
        $rows = ExaPointTransaction::query()
            ->where('user_id', $userId)
            ->latest('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function adminSuspicious(): JsonResponse
    {
        $highFrequency = ExaPointTransaction::query()
            ->select(['user_id', DB::raw('COUNT(*) as tx_count')])
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 20')
            ->get();

        $largeAdjustments = ExaPointTransaction::query()
            ->where('type', 'adjust')
            ->whereRaw('ABS(CAST(amount as REAL)) >= 10000')
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => [
                'high_frequency_users' => $highFrequency,
                'large_adjustments' => $largeAdjustments,
            ],
        ]);
    }
}

