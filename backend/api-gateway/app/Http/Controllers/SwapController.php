<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Swap;
use App\Services\SwapEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SwapController extends Controller
{
    public function __construct(private readonly SwapEngineService $swapEngineService)
    {
    }

    public function quote(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'from_currency' => ['required', 'string', 'max:16'],
            'to_currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $quote = $this->swapEngineService->createQuote(
                $request->user()->id,
                $payload['from_currency'],
                $payload['to_currency'],
                (string) $payload['amount']
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'quote_id' => $quote->quote_id,
            'rate' => $quote->rate,
            'receive_amount' => $quote->amount_received,
            'fee' => $quote->fee,
            'expires_in' => max(0, now()->diffInSeconds($quote->expires_at, false)),
        ], 201);
    }

    public function execute(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'quote_id' => ['required', 'uuid'],
        ]);

        try {
            $swap = $this->swapEngineService->execute(
                $request->user()->id,
                $payload['quote_id'],
                $request->header('X-Idempotency-Key')
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Swap queued for execution.',
            'data' => $swap,
        ], 202);
    }

    public function show(Request $request, string $swapId): JsonResponse
    {
        $swap = Swap::query()
            ->where('swap_id', $swapId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$swap) {
            return response()->json(['message' => 'Swap not found.'], 404);
        }

        return response()->json(['data' => $swap]);
    }
}
