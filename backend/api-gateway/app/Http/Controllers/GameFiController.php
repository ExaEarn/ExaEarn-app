<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GameFiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GameFiController extends Controller
{
    public function __construct(private readonly GameFiService $gameFiService)
    {
    }

    public function lotteryGames(): JsonResponse
    {
        return response()->json(['data' => $this->gameFiService->lotteryGames()]);
    }

    public function lotteryGame(int $gameId): JsonResponse
    {
        return response()->json(['data' => $this->gameFiService->lotteryGame($gameId)]);
    }

    public function createLotteryGame(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'entry_fee_eth' => ['required', 'numeric', 'gt:0'],
            'max_players' => ['nullable', 'integer', 'min:2'],
            'trigger_type' => ['required', 'string', 'in:max_players,timed'],
            'draw_at' => ['nullable', 'date'],
            'rolling_interval_seconds' => ['nullable', 'integer', 'min:60'],
        ]);

        $game = $this->gameFiService->createLotteryGame($payload);
        return response()->json(['data' => $game], 201);
    }

    public function joinLottery(Request $request, int $gameId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:100'],
            'entry_tx_hash' => ['sometimes', 'string', 'max:100'],
            'entry_amount_eth' => ['required_with:entry_tx_hash', 'numeric', 'gt:0'],
            'network' => ['sometimes', 'string', 'max:40'],
        ]);

        try {
            $entry = isset($payload['entry_tx_hash'])
                ? $this->gameFiService->recordLotteryEntry($request->user(), $gameId, $payload)
                : $this->gameFiService->enterLottery($request->user(), $gameId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $entry], 202);
    }

    public function enterLottery(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'game_id' => ['required', 'integer', 'min:1'],
            'wallet_address' => ['required', 'string', 'max:100'],
            'network' => ['sometimes', 'string', 'max:40'],
        ]);

        try {
            $entry = $this->gameFiService->enterLottery($request->user(), (int) $payload['game_id'], $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $entry], 202);
    }

    public function bettingPools(): JsonResponse
    {
        return response()->json(['data' => $this->gameFiService->bettingPools()]);
    }

    public function createBettingPool(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_name' => ['required', 'string', 'max:180'],
            'bet_options' => ['required', 'array', 'min:2'],
            'bet_options.*' => ['required', 'string', 'max:80'],
            'entry_fee_eth' => ['nullable', 'numeric', 'gte:0'],
            'locking_at' => ['nullable', 'date'],
        ]);

        $pool = $this->gameFiService->createBettingPool($payload);
        return response()->json(['data' => $pool], 201);
    }

    public function placeBet(Request $request, int $poolId): JsonResponse
    {
        $payload = $request->validate([
            'wallet_address' => ['required', 'string', 'max:100'],
            'entry_tx_hash' => ['required', 'string', 'max:100'],
            'bet_option' => ['required', 'string', 'max:80'],
            'bet_amount_eth' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $bet = $this->gameFiService->recordBet($request->user(), $poolId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $bet], 202);
    }

    public function resolveBettingPool(Request $request, int $poolId): JsonResponse
    {
        $payload = $request->validate([
            'winning_option' => ['required', 'string', 'max:80'],
        ]);

        try {
            $pool = $this->gameFiService->resolveBettingPool($poolId, $payload);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $pool]);
    }
}
