<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmartOrderRoutingLog;

class SmartOrderRoutingService
{
    public function __construct(
        private readonly TradeService $tradeService,
        private readonly ExternalLiquidityProviderService $external,
        private readonly PriceAggregationService $aggregation,
        private readonly ExecutionDecisionService $decision,
        private readonly OrderSplittingService $splitter,
        private readonly SlippageProtectionService $slippage,
    ) {
    }

    public function routeOrder(int $userId, array $order): array
    {
        $start = microtime(true);
        $symbol = strtoupper((string) $order['pair']);
        $side = strtolower((string) $order['side']);
        $qty = (string) $order['amount'];

        $internalBook = $this->tradeService->getOrderBook($symbol, 30);
        $externalBook = $this->external->fetchOrderBook(str_replace('/', '', $symbol), 30);
        $sources = $this->aggregation->aggregate(str_replace('/', '', $symbol), $internalBook, $externalBook);

        $exec = $this->decision->decide($side, $qty, $sources, $this->splitter);
        $plan = $exec['plan'] ?? [];

        $requested = (float) $qty;
        $filled = 0.0;
        $weighted = 0.0;
        $steps = [];

        foreach ($plan as $step) {
            $stepQty = (float) ($step['quantity'] ?? 0);
            if ($stepQty <= 0) {
                continue;
            }

            if ($step['source'] === 'internal') {
                $result = $this->tradeService->placeOrder(
                    $userId,
                    $symbol,
                    $side,
                    'market',
                    (string) $stepQty,
                    null,
                    ['source' => 'sor', 'sor' => true]
                );

                $execPrice = $this->extractInternalAvgPrice($result);
                $filledQty = $this->extractInternalFilledQty($result);
                $steps[] = ['source' => 'internal', 'qty' => $filledQty, 'price' => $execPrice];
            } else {
                $external = $this->external->placeExternalOrder([
                    'symbol' => str_replace('/', '', $symbol),
                    'side' => strtoupper($side),
                    'type' => 'MARKET',
                    'quantity' => $stepQty,
                    'price' => $step['price'] ?? null,
                ]);

                if (($external['status'] ?? '') !== 'filled') {
                    $fallback = $this->tradeService->placeOrder(
                        $userId,
                        $symbol,
                        $side,
                        'market',
                        (string) $stepQty,
                        null,
                        ['source' => 'sor_fallback', 'sor' => true]
                    );
                    $execPrice = $this->extractInternalAvgPrice($fallback);
                    $filledQty = $this->extractInternalFilledQty($fallback);
                    $steps[] = ['source' => 'internal_fallback', 'qty' => $filledQty, 'price' => $execPrice];
                } else {
                    $execPrice = (float) ($external['executed_price'] ?? $step['price'] ?? 0);
                    $filledQty = (float) ($external['executed_qty'] ?? $stepQty);
                    $steps[] = ['source' => 'binance', 'qty' => $filledQty, 'price' => $execPrice];
                }
            }

            $last = end($steps);
            $filled += (float) ($last['qty'] ?? 0);
            $weighted += ((float) ($last['qty'] ?? 0)) * ((float) ($last['price'] ?? 0));
        }

        $avg = $filled > 0 ? ($weighted / $filled) : 0;
        $bestExpected = $this->expectedBestPrice($side, $sources);
        $slippage = $this->slippage->assertWithin($side, $bestExpected, $avg, isset($order['max_slippage']) ? (float) $order['max_slippage'] : null);

        $duration = (int) round((microtime(true) - $start) * 1000);

        SmartOrderRoutingLog::query()->create([
            'user_id' => $userId,
            'symbol' => $symbol,
            'side' => $side,
            'requested_quantity' => (string) $requested,
            'executed_quantity' => (string) $filled,
            'avg_execution_price' => (string) $avg,
            'expected_best_price' => (string) $bestExpected,
            'slippage_percent' => (string) $slippage,
            'execution_time_ms' => $duration,
            'route_plan' => $plan,
            'execution_result' => $steps,
            'status' => $filled > 0 ? 'success' : 'failed',
        ]);

        return [
            'mode' => 'smart_order_routing',
            'symbol' => $symbol,
            'requested_quantity' => $requested,
            'executed_quantity' => $filled,
            'avg_execution_price' => $avg,
            'expected_best_price' => $bestExpected,
            'slippage_percent' => $slippage,
            'execution_time_ms' => $duration,
            'plan' => $plan,
            'executions' => $steps,
        ];
    }

    private function expectedBestPrice(string $side, array $sources): float
    {
        $prices = array_map(fn ($s) => (float) ($side === 'buy' ? ($s['best_ask'] ?? 0) : ($s['best_bid'] ?? 0)), $sources);
        $prices = array_filter($prices, fn ($v) => $v > 0);
        if ($prices === []) {
            return 0.0;
        }

        return $side === 'buy' ? min($prices) : max($prices);
    }

    private function extractInternalAvgPrice(array $result): float
    {
        $trades = $result['trades'] ?? [];
        if ($trades === []) {
            return 0.0;
        }

        $qty = 0.0;
        $weighted = 0.0;
        foreach ($trades as $t) {
            $a = (float) ($t['amount'] ?? 0);
            $p = (float) ($t['price'] ?? 0);
            $qty += $a;
            $weighted += $a * $p;
        }

        return $qty > 0 ? ($weighted / $qty) : 0.0;
    }

    private function extractInternalFilledQty(array $result): float
    {
        $trades = $result['trades'] ?? [];
        $qty = 0.0;
        foreach ($trades as $t) {
            $qty += (float) ($t['amount'] ?? 0);
        }
        return $qty;
    }
}
