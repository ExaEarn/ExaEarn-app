<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use App\Models\FuturesOrder;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotOrder;
use App\Models\MarketMakerBotPerformanceSnapshot;
use App\Models\MarketMakerBotQuoteCycle;
use App\Models\MarketMakerBotStrategy;
use App\Models\MarketMakerBotStrategyVersion;
use App\Models\MarketMakerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MarketMakerBotService
{
    private const BOT_TRANSITIONS = [
        'DRAFT' => ['BACKTEST', 'SHADOW', 'RETIRED'],
        'BACKTEST' => ['SHADOW', 'DRAFT', 'RETIRED'],
        'SHADOW' => ['APPROVED', 'PAUSED', 'RETIRED'],
        'APPROVED' => ['ACTIVE', 'PAUSED', 'RETIRED'],
        'ACTIVE' => ['LIMIT_NEW_RISK', 'REDUCE_ONLY', 'PAUSED', 'EMERGENCY', 'RETIRED'],
        'LIMIT_NEW_RISK' => ['ACTIVE', 'REDUCE_ONLY', 'PAUSED', 'EMERGENCY'],
        'REDUCE_ONLY' => ['ACTIVE', 'PAUSED', 'EMERGENCY'],
        'PAUSED' => ['SHADOW', 'APPROVED', 'ACTIVE', 'RETIRED'],
        'EMERGENCY' => ['PAUSED', 'REDUCE_ONLY'],
    ];

    public function __construct(
        private readonly InstitutionalService $institutions,
        private readonly MarketMakerQuoteEngine $quoteEngine,
        private readonly TradeService $spot,
        private readonly FuturesOrderService $futures,
        private readonly MarketMakerCancelReplaceService $cancelReplace,
        private readonly MarketMakerCrossProductRiskService $crossProductRisk,
        private readonly MarketMakerFuturesRiskService $futuresRisk,
        private readonly InstitutionalRealtimeService $realtime,
    ) {
    }

    public function createStrategy(User $actor, InstitutionalAccount $institution, MarketMakerProfile $profile, array $payload): MarketMakerBotStrategy
    {
        $this->assertProfile($institution, $profile);
        $subaccount = InstitutionalSubaccount::query()->findOrFail($profile->subaccount_id);
        $this->institutions->assertSubaccountPermission($actor, $subaccount, 'CREATE_API_KEY');

        return DB::transaction(function () use ($actor, $institution, $payload, $profile): MarketMakerBotStrategy {
            $strategy = MarketMakerBotStrategy::query()->create([
                'strategy_uuid' => (string) Str::uuid(),
                'institution_id' => $institution->id,
                'market_maker_id' => $profile->id,
                'name' => (string) $payload['name'],
                'strategy_type' => strtoupper((string) ($payload['strategy_type'] ?? 'TWO_SIDED_MARKET_MAKING')),
                'status' => 'DRAFT',
                'supported_markets' => array_map('strtoupper', $payload['supported_markets'] ?? $profile->approved_markets ?? []),
                'parameters' => $payload['parameters'] ?? [],
                'created_by_user_id' => $actor->id,
            ]);
            MarketMakerBotStrategyVersion::query()->create([
                'version_uuid' => (string) Str::uuid(),
                'strategy_id' => $strategy->id,
                'version' => 1,
                'status' => 'DRAFT',
                'parameters' => $strategy->parameters,
                'supported_markets' => $strategy->supported_markets,
                'created_by_user_id' => $actor->id,
            ]);
            $this->publish($institution, 'mm_bot.strategy', ['strategy_uuid' => $strategy->strategy_uuid, 'status' => $strategy->status]);

            return $strategy->fresh();
        });
    }

    public function createBot(User $actor, InstitutionalAccount $institution, MarketMakerProfile $profile, MarketMakerBotStrategy $strategy, array $payload): MarketMakerBot
    {
        $this->assertProfile($institution, $profile);
        if ((int) $strategy->market_maker_id !== (int) $profile->id) {
            throw new RuntimeException('Strategy does not belong to the selected market maker.');
        }
        $subaccount = InstitutionalSubaccount::query()->findOrFail($profile->subaccount_id);
        $this->institutions->assertSubaccountPermission($actor, $subaccount, 'CREATE_API_KEY');
        $symbol = strtoupper((string) $payload['market_symbol']);
        if (! in_array($symbol, $profile->approved_markets ?? [], true)) {
            throw new RuntimeException('Market-maker bot market is not approved for this profile.');
        }
        $version = MarketMakerBotStrategyVersion::query()->where('strategy_id', $strategy->id)->latest('version')->firstOrFail();

        $bot = MarketMakerBot::query()->create([
            'bot_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'market_maker_id' => $profile->id,
            'subaccount_id' => $profile->subaccount_id,
            'api_key_id' => $payload['api_key_id'] ?? null,
            'strategy_id' => $strategy->id,
            'strategy_version_id' => $version->id,
            'name' => (string) $payload['name'],
            'market_symbol' => $symbol,
            'product_type' => strtoupper((string) ($payload['product_type'] ?? 'SPOT')),
            'ownership_type' => strtoupper((string) ($payload['ownership_type'] ?? 'INSTITUTION_MANAGED')),
            'status' => 'DRAFT',
            'safety_state' => 'NORMAL',
            'configuration' => $payload['configuration'] ?? [],
            'risk_limits' => $payload['risk_limits'] ?? [],
            'created_by_user_id' => $actor->id,
        ]);
        $this->publish($institution, 'mm_bot.status', ['bot_uuid' => $bot->bot_uuid, 'status' => $bot->status]);

        return $bot->fresh();
    }

    public function approve(Admin $admin, MarketMakerBot $bot, string $reason): MarketMakerBot
    {
        $strategy = MarketMakerBotStrategy::query()->findOrFail($bot->strategy_id);
        $version = MarketMakerBotStrategyVersion::query()->findOrFail($bot->strategy_version_id);
        $profile = MarketMakerProfile::query()->findOrFail($bot->market_maker_id);
        if ($profile->status !== 'ACTIVE' || ! in_array($profile->safety_mode, ['NORMAL', null], true)) {
            throw new RuntimeException('Market-maker profile is not eligible for bot approval.');
        }
        $strategy->forceFill(['status' => 'APPROVED', 'approved_by_admin_id' => $admin->id, 'approved_at' => now()])->save();
        $version->forceFill(['status' => 'APPROVED', 'approved_by_admin_id' => $admin->id, 'approved_at' => now()])->save();
        $bot->forceFill(['status' => 'APPROVED', 'approved_by_admin_id' => $admin->id, 'approved_at' => now()])->save();
        $this->institutions->audit($bot->institution_id, $bot->subaccount_id, 'admin', $admin->id, 'market_maker_bot.approved', 'market_maker_bot', $bot->id, null, $bot->fresh()->toArray(), $reason);
        $this->publish(InstitutionalAccount::query()->findOrFail($bot->institution_id), 'mm_bot.status', ['bot_uuid' => $bot->bot_uuid, 'status' => 'APPROVED']);

        return $bot->fresh();
    }

    public function transition(User|Admin $actor, MarketMakerBot $bot, string $status, string $reason): MarketMakerBot
    {
        $status = strtoupper($status);
        $current = (string) $bot->status;
        if (! in_array($status, self::BOT_TRANSITIONS[$current] ?? [], true)) {
            throw new RuntimeException("Invalid market-maker bot transition {$current} -> {$status}.");
        }
        $before = $bot->toArray();
        $bot->forceFill([
            'status' => $status,
            'safety_state' => match ($status) {
                'LIMIT_NEW_RISK' => 'LIMIT_NEW_RISK',
                'REDUCE_ONLY' => 'REDUCE_ONLY',
                'PAUSED' => 'PAUSED',
                'EMERGENCY' => 'EMERGENCY',
                default => $bot->safety_state,
            },
        ])->save();
        $this->institutions->audit($bot->institution_id, $bot->subaccount_id, $actor instanceof Admin ? 'admin' : 'user', $actor->id, 'market_maker_bot.transitioned', 'market_maker_bot', $bot->id, $before, $bot->fresh()->toArray(), $reason);
        $this->publish(InstitutionalAccount::query()->findOrFail($bot->institution_id), 'mm_bot.status', ['bot_uuid' => $bot->bot_uuid, 'status' => $status]);

        return $bot->fresh();
    }

    public function acquireLease(MarketMakerBot $bot, string $workerId, int $ttlSeconds = 30): bool
    {
        return DB::transaction(function () use ($bot, $ttlSeconds, $workerId): bool {
            $locked = MarketMakerBot::query()->whereKey($bot->id)->lockForUpdate()->firstOrFail();
            if ($locked->worker_lease_expires_at && $locked->worker_lease_expires_at->isFuture() && $locked->worker_id !== $workerId) {
                return false;
            }
            $locked->forceFill(['worker_id' => $workerId, 'worker_lease_expires_at' => now()->addSeconds($ttlSeconds), 'last_heartbeat_at' => now()])->save();

            return true;
        });
    }

    public function runQuoteCycle(MarketMakerBot $bot, string $mode, string $idempotencyKey): MarketMakerBotQuoteCycle
    {
        $existing = MarketMakerBotQuoteCycle::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->fresh();
        }
        $mode = strtoupper($mode);
        if ($mode === 'LIVE' && ! in_array($bot->status, ['ACTIVE', 'LIMIT_NEW_RISK', 'REDUCE_ONLY'], true)) {
            throw new RuntimeException('Only ACTIVE market-maker bots can submit live quotes.');
        }
        if ($mode === 'SHADOW' && ! in_array($bot->status, ['DRAFT', 'BACKTEST', 'SHADOW', 'APPROVED', 'ACTIVE'], true)) {
            throw new RuntimeException('Bot state does not allow shadow quote calculation.');
        }
        $plan = $this->quoteEngine->plan($bot);

        return DB::transaction(function () use ($bot, $idempotencyKey, $mode, $plan): MarketMakerBotQuoteCycle {
            $existing = MarketMakerBotQuoteCycle::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->fresh();
            }
            $cycle = MarketMakerBotQuoteCycle::query()->create([
                'cycle_uuid' => (string) Str::uuid(),
                'bot_id' => $bot->id,
                'strategy_version_id' => $bot->strategy_version_id,
                'mode' => $mode,
                'status' => $mode === 'SHADOW' ? 'SHADOW_RECORDED' : 'PLANNED',
                'market_symbol' => $bot->market_symbol,
                'fair_value' => $plan['fair_value']['fair_value'],
                'spread_bps' => $plan['spread_bps'],
                'market_snapshot' => $plan['fair_value'],
                'inventory_snapshot' => $plan['inventory'],
                'risk_snapshot' => $plan['risk'],
                'quote_plan' => $plan['quotes'],
                'submitted_orders' => [],
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addSeconds((int) $plan['ttl_seconds']),
            ]);
            if ($mode === 'LIVE') {
                $cycle = $this->submitQuotes($bot, $cycle);
            }
            $this->publish(InstitutionalAccount::query()->findOrFail($bot->institution_id), 'mm_bot.quote', ['bot_uuid' => $bot->bot_uuid, 'cycle_uuid' => $cycle->cycle_uuid, 'mode' => $mode, 'status' => $cycle->status]);

            return $cycle->fresh();
        });
    }

    public function snapshotPerformance(MarketMakerBot $bot): MarketMakerBotPerformanceSnapshot
    {
        return MarketMakerBotPerformanceSnapshot::query()->create([
            'snapshot_uuid' => (string) Str::uuid(),
            'bot_id' => $bot->id,
            'market_symbol' => $bot->market_symbol,
            'maker_volume' => '0',
            'realized_pnl' => '0',
            'unrealized_pnl' => '0',
            'fees' => '0',
            'rebates' => '0',
            'drawdown_bps' => '0',
            'cancel_ratio' => '0',
            'metadata' => ['pnl_source' => 'real_fills_only', 'deposits_are_not_profit' => true],
            'measured_at' => now(),
        ]);
    }

    public function massCancel(MarketMakerBot $bot, string $reason): array
    {
        $bot->forceFill(['safety_state' => in_array($bot->safety_state, ['EMERGENCY', 'PAUSED'], true) ? $bot->safety_state : 'REDUCE_ONLY'])->save();
        $result = $this->cancelReplace->massCancel($bot->fresh(), $reason);
        $this->publish(InstitutionalAccount::query()->findOrFail($bot->institution_id), 'mm_bot.order', ['bot_uuid' => $bot->bot_uuid, 'action' => 'mass_cancel', 'result' => $result]);

        return $result;
    }

    private function submitQuotes(MarketMakerBot $bot, MarketMakerBotQuoteCycle $cycle): MarketMakerBotQuoteCycle
    {
        if ($bot->product_type === 'FUTURES') {
            $cross = $this->crossProductRisk->exposure($bot);
            $this->futuresRisk->assertCanQuote($bot, $cycle->market_snapshot ?? [], $cross, false);
        }

        return $this->cancelReplace->reconcile($bot, $cycle, fn (array $quote): array => $this->submitQuote($bot, $cycle, $quote));
    }

    private function submitQuote(MarketMakerBot $bot, MarketMakerBotQuoteCycle $cycle, array $quote): array
    {
        $institution = InstitutionalAccount::query()->findOrFail($bot->institution_id);
        $clientOrderId = 'mm-bot-'.$bot->bot_uuid.'-'.$cycle->cycle_uuid.'-'.$quote['side'].'-'.$quote['level'];
        $botOrder = MarketMakerBotOrder::query()->create([
                'bot_order_uuid' => (string) Str::uuid(),
                'bot_id' => $bot->id,
                'quote_cycle_id' => $cycle->id,
                'client_order_id' => $clientOrderId,
                'side' => $quote['side'],
                'order_type' => 'LIMIT',
                'price' => $quote['price'],
                'quantity' => $quote['quantity'],
                'status' => 'SUBMITTING',
                'metadata' => ['source' => 'market_maker_bot', 'no_privileged_matching' => true, 'level' => $quote['level'], 'product_type' => $bot->product_type],
            ]);
        if ($bot->product_type === 'SPOT') {
            $result = $this->spot->placeOrder((int) $institution->master_user_id, $bot->market_symbol, strtolower((string) $quote['side']), 'limit', (string) $quote['quantity'], (string) $quote['price'], [
                'client_order_id' => $clientOrderId,
                'post_only' => true,
                'time_in_force' => 'GTC',
                'market_maker_bot_id' => $bot->id,
                'market_maker_strategy_version_id' => $bot->strategy_version_id,
                'quote_cycle_uuid' => $cycle->cycle_uuid,
                'account_type' => 'unified_trading',
            ]);
            $order = $result['order'] ?? null;
            $botOrder->forceFill(['spot_order_id' => $order?->id, 'status' => 'SUBMITTED'])->save();

            return ['client_order_id' => $clientOrderId, 'spot_order_id' => $order?->id, 'status' => 'SUBMITTED'];
        }

        if ($bot->product_type !== 'FUTURES') {
            throw new RuntimeException('Unsupported market-maker bot product type.');
        }
        $futuresOrder = $this->futures->placeOrder((int) $institution->master_user_id, [
            'symbol' => (string) ($bot->configuration['futures_market_symbol'] ?? str_replace('/', '', $bot->market_symbol).'PERP'),
            'type' => 'limit',
            'side' => $quote['side'] === 'BUY' ? 'long' : 'short',
            'quantity' => (string) $quote['quantity'],
            'price' => (string) $quote['price'],
            'leverage' => (int) ($bot->configuration['futures_leverage'] ?? 1),
            'time_in_force' => 'GTC',
            'post_only' => true,
            'reduce_only' => false,
            'source' => 'market_maker_bot',
            'metadata' => [
                'client_order_id' => $clientOrderId,
                'bot_id' => $bot->id,
                'market_maker_strategy_version_id' => $bot->strategy_version_id,
                'quote_cycle_uuid' => $cycle->cycle_uuid,
            ],
        ]);
        $botOrder->forceFill(['futures_order_id' => $futuresOrder->id, 'status' => 'SUBMITTED'])->save();

        return ['client_order_id' => $clientOrderId, 'futures_order_id' => $futuresOrder->id, 'status' => 'SUBMITTED'];
    }

    private function assertProfile(InstitutionalAccount $institution, MarketMakerProfile $profile): void
    {
        if ((int) $profile->institution_id !== (int) $institution->id || $profile->status !== 'ACTIVE') {
            throw new RuntimeException('Market-maker profile is not active for this institution.');
        }
    }

    private function publish(InstitutionalAccount $institution, string $event, array $payload): void
    {
        $this->realtime->publish((int) $institution->master_user_id, "institution.{$institution->id}.mm_bot", $event, $payload);
    }
}
