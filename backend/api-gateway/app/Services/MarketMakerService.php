<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LiquidityPool;
use App\Models\Market;
use App\Models\MarketData;
use App\Models\MarketMakerConfig;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class MarketMakerService
{
    public function __construct(
        private readonly SpreadEngineService $spreadEngine,
        private readonly OrderBookDepthService $depthService,
        private readonly PriceAnchorService $anchorService,
        private readonly MarketStreamService $stream,
    ) {
    }

    public function runForSymbol(string $symbol): array
    {
        $telemetry = $this->telemetryForSymbol($symbol);
        if (($telemetry['status'] ?? '') !== 'ready') {
            return $telemetry;
        }

        /** @var Market $market */
        $market = $telemetry['market'];
        /** @var MarketMakerConfig $config */
        $config = $telemetry['config'];

        $dynamicSpread = (float) $telemetry['dynamic_spread_percent'];
        $anchor = (float) $telemetry['anchor_price'];
        $levels = $this->depthService->generateDepthLevels($anchor, $dynamicSpread, (float) $config->order_size, (int) $config->max_orders);
        [$safeLevels, $guard] = $this->applyExposureGuards($market, $levels);

        if (($guard['blocked'] ?? false) === true) {
            return ['symbol' => $market->symbol, 'status' => 'blocked_by_risk', 'guard' => $guard, 'telemetry' => $telemetry];
        }

        $this->replaceSyntheticOrders($market, $safeLevels);
        $this->depthService->updateSnapshot($market);

        $this->stream->publish([
            'type' => 'spread_update',
            'pair' => $market->symbol,
            'data' => [
                'spread_percent' => $dynamicSpread,
                'anchor_price' => $anchor,
                'timestamp' => now()->toISOString(),
            ],
        ]);

        return ['symbol' => $market->symbol, 'status' => 'ok', 'spread_percent' => $dynamicSpread, 'guard' => $guard];
    }

    public function telemetryForSymbol(string $symbol): array
    {
        $market = Market::query()->where('symbol', strtoupper($symbol))->first();
        if (!$market) {
            return ['symbol' => strtoupper($symbol), 'status' => 'missing_market'];
        }

        $config = MarketMakerConfig::query()->where('symbol', $market->symbol)->where('status', 'active')->first();
        if (!$config) {
            return ['symbol' => $market->symbol, 'status' => 'skipped_no_config'];
        }

        $latest = MarketData::query()->where('symbol', $market->symbol)->latest('timestamp')->first();
        $volatility = (float) ($latest?->volatility ?? 0.01);
        $volume = (float) ($latest?->volume ?? 1000);

        $dynamicSpread = $this->spreadEngine->calculate($volatility, $volume);
        $basePrice = (float) ($market->last_price ?: ($latest?->price ?? 0));
        if ($basePrice <= 0) {
            return ['symbol' => $market->symbol, 'status' => 'skipped_no_price'];
        }

        $anchor = $this->anchorService->anchor($market->symbol, $basePrice);
        $levels = $this->depthService->generateDepthLevels($anchor, $dynamicSpread, (float) $config->order_size, (int) $config->max_orders);
        [, $guard] = $this->applyExposureGuards($market, $levels);

        return [
            'status' => 'ready',
            'symbol' => $market->symbol,
            'market' => $market,
            'config' => $config,
            'dynamic_spread_percent' => $dynamicSpread,
            'anchor_price' => $anchor,
            'base_price' => $basePrice,
            'volume' => $volume,
            'volatility' => $volatility,
            'guard' => $guard,
        ];
    }

    private function applyExposureGuards(Market $market, array $levels): array
    {
        $maxNotional = (float) config('market_maker.risk.max_notional_per_market', 500000);
        $poolUsageLimit = ((float) config('market_maker.risk.pool_usage_limit_percent', 80)) / 100;
        $minLevels = (int) config('market_maker.risk.min_depth_levels', 2);

        $neededBase = array_sum(array_map(fn ($x) => (float) $x['amount'], $levels['asks']));
        $neededQuote = array_sum(array_map(fn ($x) => ((float) $x['amount']) * ((float) $x['price']), $levels['bids']));
        $newNotional = $neededQuote + array_sum(array_map(fn ($x) => ((float) $x['amount']) * ((float) $x['price']), $levels['asks']));

        $existingNotional = (float) Order::query()
            ->where('market_id', $market->id)
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('metadata->source', 'market_maker')
            ->get()
            ->sum(fn (Order $o) => ((float) $o->remaining_amount) * ((float) $o->price));

        $notionalRoom = max($maxNotional - $existingNotional, 0.0);
        $notionalScale = $newNotional > 0 ? min(1.0, $notionalRoom / $newNotional) : 1.0;

        $pool = LiquidityPool::query()->where('symbol', $market->symbol)->first();
        $baseCap = $pool ? ((float) $pool->base_asset_balance) * $poolUsageLimit : INF;
        $quoteCap = $pool ? ((float) $pool->quote_asset_balance) * $poolUsageLimit : INF;
        $baseScale = $neededBase > 0 ? min(1.0, $baseCap / $neededBase) : 1.0;
        $quoteScale = $neededQuote > 0 ? min(1.0, $quoteCap / $neededQuote) : 1.0;

        $scale = min($notionalScale, $baseScale, $quoteScale);
        if ($scale <= 0) {
            return [[], ['blocked' => true, 'reason' => 'no_exposure_room_or_pool_capacity']];
        }

        $safe = $this->scaleLevels($levels, $scale, $minLevels);
        return [$safe, [
            'blocked' => false,
            'scale' => $scale,
            'existing_notional' => $existingNotional,
            'max_notional' => $maxNotional,
            'pool_found' => (bool) $pool,
        ]];
    }

    private function scaleLevels(array $levels, float $scale, int $minLevels): array
    {
        $trimCount = max($minLevels, (int) floor(count($levels['bids']) * $scale));
        $bids = array_slice($levels['bids'], 0, $trimCount);
        $asks = array_slice($levels['asks'], 0, $trimCount);

        $bids = array_map(function (array $row) use ($scale): array {
            $row['amount'] = round(max(0.00000001, ((float) $row['amount']) * $scale), 8);
            return $row;
        }, $bids);

        $asks = array_map(function (array $row) use ($scale): array {
            $row['amount'] = round(max(0.00000001, ((float) $row['amount']) * $scale), 8);
            return $row;
        }, $asks);

        return ['bids' => $bids, 'asks' => $asks];
    }

    private function replaceSyntheticOrders(Market $market, array $levels): void
    {
        $userId = (int) config('market_maker.system_user_id', 1);

        DB::transaction(function () use ($market, $levels, $userId): void {
            Order::query()
                ->where('market_id', $market->id)
                ->whereIn('status', ['open', 'partially_filled'])
                ->where('metadata->source', 'market_maker')
                ->update(['status' => 'cancelled']);

            foreach ($levels['bids'] as $level) {
                $this->createOrder($market, $userId, 'buy', (string) $level['price'], (string) $level['amount']);
            }
            foreach ($levels['asks'] as $level) {
                $this->createOrder($market, $userId, 'sell', (string) $level['price'], (string) $level['amount']);
            }
        });
    }

    private function createOrder(Market $market, int $userId, string $side, string $price, string $amount): void
    {
        Order::query()->create([
            'order_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'market_id' => $market->id,
            'pair' => $market->symbol,
            'side' => $side,
            'type' => 'limit',
            'trigger_order_type' => null,
            'price' => $price,
            'stop_price' => null,
            'amount' => $amount,
            'filled_amount' => '0',
            'remaining_amount' => $amount,
            'locked_amount' => '0',
            'locked_currency' => $side === 'buy' ? $market->quote_currency : $market->base_currency,
            'status' => 'open',
            'metadata' => ['source' => 'market_maker', 'synthetic' => true],
        ]);
    }
}
