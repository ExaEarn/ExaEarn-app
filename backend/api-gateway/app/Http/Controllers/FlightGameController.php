<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FlightGameBet;
use App\Services\FlightGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FlightGameController extends Controller
{
    public function __construct(private readonly FlightGameService $flightGame)
    {
    }

    public function state(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->state($request->user())]);
    }

    public function history(): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->history()]);
    }

    public function myBets(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->myBets((int) $request->user()->id)]);
    }

    public function placeBet(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'asset' => ['required', 'string', 'max:16'],
            'stake' => ['required', 'numeric', 'gt:0'],
            'panel_slot' => ['nullable', 'integer', 'min:1', 'max:2'],
            'auto_cashout' => ['nullable', 'numeric', 'gte:1'],
        ]);

        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');
        $wasReplay = $idempotencyKey !== ''
            && FlightGameBet::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('user_id', (int) $request->user()->id)
                ->exists();

        try {
            $bet = $this->flightGame->placeBet(
                $request->user(),
                $payload,
                $idempotencyKey
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $bet], $wasReplay ? 200 : 201);
    }

    public function cashOut(Request $request, string $betUuid): JsonResponse
    {
        try {
            $bet = $this->flightGame->cashOut($request->user(), $betUuid);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $bet]);
    }

    public function fairness(string $roundUuid): JsonResponse
    {
        return response()->json(['data' => $this->flightGame->fairness($roundUuid)]);
    }
}
