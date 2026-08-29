<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FlightGameBet;
use App\Services\FlightGameService;
use App\Services\FlightGamePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FlightGameController extends Controller
{
    public function __construct(
        private readonly FlightGameService $flightGame,
        private readonly FlightGamePolicyService $policy,
    )
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
            'mode' => ['nullable', 'string', 'in:demo,real,sandbox'],
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

    public function selfExclude(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:COOLDOWN,SELF_EXCLUDED,PERMANENTLY_EXCLUDED'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason_category' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $profile = $this->policy->selfExclude(
                $request->user(),
                $payload['status'],
                $payload['expires_at'] ?? null,
                $payload['reason_category'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $profile], 201);
    }
}
