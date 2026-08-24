<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotHedge;
use App\Models\MarketMakerBotIncident;
use App\Models\MarketMakerBotQuoteCycle;
use App\Models\MarketMakerBotRebalance;
use App\Services\MarketMakerBotLoadTestService;
use App\Services\MarketMakerBotService;
use App\Services\MarketMakerMarketShockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MarketMakerBotOperationsController extends Controller
{
    public function __construct(
        private readonly MarketMakerBotService $bots,
        private readonly MarketMakerMarketShockService $shocks,
        private readonly MarketMakerBotLoadTestService $loadTests,
    )
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'bots' => MarketMakerBot::query()->count(),
            'active_bots' => MarketMakerBot::query()->where('status', 'ACTIVE')->count(),
            'shadow_cycles' => MarketMakerBotQuoteCycle::query()->where('mode', 'SHADOW')->count(),
            'live_cycles' => MarketMakerBotQuoteCycle::query()->where('mode', 'LIVE')->count(),
            'hedges' => MarketMakerBotHedge::query()->count(),
            'rebalances' => MarketMakerBotRebalance::query()->count(),
            'open_incidents' => MarketMakerBotIncident::query()->where('status', 'OPEN')->count(),
            'no_privileged_orderbook_access' => true,
        ]]);
    }

    public function bots(Request $request): JsonResponse
    {
        return response()->json(['data' => MarketMakerBot::query()->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function approve(Request $request, string $botUuid): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bot = MarketMakerBot::query()->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->approve($this->admin($request), $bot, $payload['reason']));
    }

    public function transition(Request $request, string $botUuid): JsonResponse
    {
        $payload = $request->validate(['status' => ['required', 'string', 'max:40'], 'reason' => ['required', 'string', 'max:1000']]);
        $bot = MarketMakerBot::query()->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->transition($this->admin($request), $bot, $payload['status'], $payload['reason']));
    }

    public function liveCycle(Request $request, string $botUuid): JsonResponse
    {
        $payload = $request->validate(['idempotency_key' => ['required', 'string', 'max:160']]);
        $bot = MarketMakerBot::query()->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->runQuoteCycle($bot, 'LIVE', $payload['idempotency_key']), 201);
    }

    public function massCancel(Request $request, string $botUuid): JsonResponse
    {
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->admin($request);
        $bot = MarketMakerBot::query()->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->massCancel($bot, $payload['reason']));
    }

    public function shock(Request $request, string $botUuid): JsonResponse
    {
        $this->admin($request);
        $bot = MarketMakerBot::query()->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->shocks->evaluate($bot));
    }

    public function loadProbe(Request $request): JsonResponse
    {
        $this->admin($request);
        $payload = $request->validate([
            'bot_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'cycles_per_bot' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);
        return $this->handle(fn () => $this->loadTests->run((int) ($payload['bot_count'] ?? 10), (int) ($payload['cycles_per_bot'] ?? 1)), 201);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin || ! $admin->hasPermission('institutional.manage')) {
            throw new RuntimeException('Institutional admin permission is required.');
        }
        return $admin;
    }

    private function handle(\Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
