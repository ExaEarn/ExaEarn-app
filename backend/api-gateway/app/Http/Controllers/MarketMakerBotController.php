<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotQuoteCycle;
use App\Models\MarketMakerBotStrategy;
use App\Models\MarketMakerProfile;
use App\Services\MarketMakerHedgeService;
use App\Services\MarketMakerMarketShockService;
use App\Services\MarketMakerRebalancingService;
use App\Services\MarketMakerBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MarketMakerBotController extends Controller
{
    public function __construct(
        private readonly MarketMakerBotService $bots,
        private readonly MarketMakerHedgeService $hedges,
        private readonly MarketMakerRebalancingService $rebalances,
        private readonly MarketMakerMarketShockService $shocks,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        return response()->json(['data' => MarketMakerBot::query()->where('institution_id', $institution->id)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function strategies(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        return response()->json(['data' => MarketMakerBotStrategy::query()->where('institution_id', $institution->id)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function createStrategy(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'market_maker_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'strategy_type' => ['nullable', 'string', 'max:80'],
            'supported_markets' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
        ]);
        $profile = MarketMakerProfile::query()->where('institution_id', $institution->id)->findOrFail($payload['market_maker_id']);
        return $this->handle(fn () => $this->bots->createStrategy($request->user(), $institution, $profile, $payload), 201);
    }

    public function store(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'market_maker_id' => ['required', 'integer'],
            'strategy_id' => ['required', 'integer'],
            'api_key_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'market_symbol' => ['required', 'string', 'max:48'],
            'product_type' => ['nullable', 'string', 'max:24'],
            'ownership_type' => ['nullable', 'string', 'max:40'],
            'configuration' => ['nullable', 'array'],
            'risk_limits' => ['nullable', 'array'],
        ]);
        $profile = MarketMakerProfile::query()->where('institution_id', $institution->id)->findOrFail($payload['market_maker_id']);
        $strategy = MarketMakerBotStrategy::query()->where('institution_id', $institution->id)->findOrFail($payload['strategy_id']);
        return $this->handle(fn () => $this->bots->createBot($request->user(), $institution, $profile, $strategy, $payload), 201);
    }

    public function show(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return response()->json(['data' => $bot->load('quoteCycles')]);
    }

    public function shadow(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['idempotency_key' => ['required', 'string', 'max:160']]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->runQuoteCycle($bot, 'SHADOW', $payload['idempotency_key']), 201);
    }

    public function start(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->transition($request->user(), $bot, 'ACTIVE', $payload['reason']));
    }

    public function pause(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->transition($request->user(), $bot, 'PAUSED', $payload['reason']));
    }

    public function reduceOnly(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->transition($request->user(), $bot, 'REDUCE_ONLY', $payload['reason']));
    }

    public function massCancel(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->bots->massCancel($bot, $payload['reason']));
    }

    public function hedge(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['idempotency_key' => ['required', 'string', 'max:160']]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->hedges->hedge($bot, $payload['idempotency_key']), 201);
    }

    public function rebalance(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'asset' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'string', 'max:80'],
            'destination_subaccount_id' => ['required', 'integer'],
            'source_subaccount_id' => ['nullable', 'integer'],
            'mode' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'approval_threshold' => ['nullable', 'string', 'max:80'],
        ]);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->rebalances->request($bot, $payload), 201);
    }

    public function shock(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return $this->handle(fn () => $this->shocks->evaluate($bot));
    }

    public function cycles(Request $request, string $botUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $bot = MarketMakerBot::query()->where('institution_id', $institution->id)->where('bot_uuid', $botUuid)->firstOrFail();
        return response()->json(['data' => MarketMakerBotQuoteCycle::query()->where('bot_id', $bot->id)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    private function institutionForUser(Request $request): InstitutionalAccount
    {
        $institution = InstitutionalAccount::query()->where('master_user_id', $request->user()->id)->whereIn('status', ['ACTIVE', 'APPROVED', 'RESTRICTED'])->first();
        if ($institution) {
            return $institution;
        }
        $membership = InstitutionalMembership::query()->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();
        return InstitutionalAccount::query()->findOrFail($membership->institution_id);
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
