<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\LiquidityRouteExecution;
use App\Models\LiquidityRoutePlan;
use App\Models\Market;
use App\Services\FinancialDecimal;
use App\Services\TradingRiskEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SmartOrderRouter
{
    public function __construct(
        private readonly ConsolidatedLiquidityBookService $books,
        private readonly TradingRiskEngine $risk,
        private readonly BestExecutionAuditService $audit,
    ) {
    }

    public function plan(Market $market, int $userId, string $side, string $quantity, array $options = []): LiquidityRoutePlan
    {
        $symbol = strtoupper((string) $market->symbol);
        $side = strtolower($side);
        $quantity = FinancialDecimal::normalize($quantity);
        $parentReference = (string) ($options['parent_reference'] ?? 'sor:' . Str::uuid());
        $idempotencyKey = (string) ($options['idempotency_key'] ?? $parentReference);

        return DB::transaction(function () use ($market, $userId, $side, $quantity, $options, $symbol, $parentReference, $idempotencyKey): LiquidityRoutePlan {
            $existing = LiquidityRoutePlan::query()
                ->where('parent_reference', $parentReference)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $this->risk->assertOrderAllowed($userId, 'spot', $market, [
                'side' => $side,
                'type' => $options['order_type'] ?? 'market',
                'quantity' => $quantity,
                'price' => $options['limit_price'] ?? null,
            ]);

            $book = $this->books->build($symbol, 50);
            $levels = $side === 'buy' ? $book['asks'] : $book['bids'];
            $route = $this->selectLevels($side, $quantity, $levels, (int) config('liquidity.routing.max_split_count', 4));
            if (! $route['complete']) {
                throw new RuntimeException('INSUFFICIENT_EXECUTABLE_LIQUIDITY');
            }

            $expectedAverage = $route['average_price'];
            $expectedCost = $route['quote_quantity'];
            $quality = $this->qualityScore($route['steps'], $book);

            $plan = LiquidityRoutePlan::query()->create([
                'route_plan_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'parent_reference' => $parentReference,
                'idempotency_key' => $idempotencyKey,
                'market_symbol' => $symbol,
                'side' => $side,
                'order_type' => (string) ($options['order_type'] ?? 'market'),
                'requested_quantity' => $quantity,
                'limit_price' => $options['limit_price'] ?? null,
                'routing_mode' => (string) ($options['routing_mode'] ?? config('liquidity.routing.default_mode')),
                'expected_average_price' => $expectedAverage,
                'expected_total_cost' => $expectedCost,
                'quality_score' => $quality,
                'status' => 'ROUTE_PLANNED',
                'sources_considered' => $book['sources'],
                'plan' => $route['steps'],
                'metadata' => ['phase' => 'phase8'],
            ]);

            foreach ($route['steps'] as $step) {
                LiquidityRouteExecution::query()->create([
                    'route_execution_id' => (string) Str::uuid(),
                    'liquidity_route_plan_id' => $plan->id,
                    'source_code' => $step['source'],
                    'source_type' => $step['source_type'],
                    'client_order_id' => 'exa_liq_' . Str::lower(Str::random(24)),
                    'planned_quantity' => $step['quantity'],
                    'planned_price' => $step['price'],
                    'status' => 'CREATED',
                    'metadata' => ['executable' => $step['executable']],
                ]);
            }

            $this->audit->record([
                'route_plan_id' => $plan->route_plan_id,
                'parent_reference' => $parentReference,
                'market_symbol' => $symbol,
                'side' => $side,
                'requested_quantity' => $quantity,
                'market_state' => ['book_built_at' => $book['built_at']],
                'sources_considered' => $book['sources'],
                'route_selected' => $route['steps'],
                'quality_score' => $quality,
            ]);

            return $plan->fresh();
        });
    }

    public function markExecutionResult(string $routePlanId, array $result): LiquidityRoutePlan
    {
        $plan = LiquidityRoutePlan::query()->where('route_plan_id', $routePlanId)->firstOrFail();
        $plan->metadata = array_merge($plan->metadata ?? [], ['execution_result' => $result]);
        $plan->status = (string) ($result['status'] ?? 'SETTLED');
        $plan->save();

        $this->audit->record([
            'route_plan_id' => $plan->route_plan_id,
            'parent_reference' => $plan->parent_reference,
            'market_symbol' => $plan->market_symbol,
            'side' => $plan->side,
            'requested_quantity' => (string) $plan->requested_quantity,
            'market_state' => ['finalized_at' => now()->toISOString()],
            'sources_considered' => $plan->sources_considered ?? [],
            'route_selected' => $plan->plan ?? [],
            'result' => $result,
            'quality_score' => (string) $plan->quality_score,
            'status' => 'FINAL',
        ]);

        return $plan->fresh();
    }

    private function selectLevels(string $side, string $quantity, array $levels, int $maxSplits): array
    {
        $remaining = $quantity;
        $filled = '0';
        $quote = '0';
        $steps = [];

        foreach ($levels as $level) {
            if (count($steps) >= $maxSplits || FinancialDecimal::compare($remaining, '0') <= 0) {
                break;
            }
            if (! (bool) ($level['executable'] ?? false)) {
                continue;
            }
            $available = FinancialDecimal::normalize((string) $level['quantity']);
            $price = FinancialDecimal::normalize((string) $level['price']);
            $take = FinancialDecimal::min($remaining, $available);
            if (FinancialDecimal::compare($take, '0') <= 0) {
                continue;
            }
            $steps[] = [
                'source' => (string) $level['source'],
                'source_type' => (string) $level['source_type'],
                'venue' => (string) $level['venue'],
                'quantity' => $take,
                'price' => $price,
                'fee_bps' => (string) ($level['fee_bps'] ?? '0'),
                'executable' => true,
            ];
            $filled = FinancialDecimal::add($filled, $take);
            $quote = FinancialDecimal::add($quote, FinancialDecimal::mul($take, $price));
            $remaining = FinancialDecimal::sub($remaining, $take);
        }

        return [
            'steps' => $steps,
            'filled_quantity' => $filled,
            'quote_quantity' => $quote,
            'average_price' => FinancialDecimal::compare($filled, '0') > 0 ? FinancialDecimal::div($quote, $filled) : '0',
            'complete' => FinancialDecimal::compare($filled, $quantity) >= 0,
        ];
    }

    private function qualityScore(array $steps, array $book): string
    {
        if ($steps === []) {
            return '0';
        }

        $internal = collect($steps)->contains(fn (array $step): bool => $step['source'] === 'EXAEARN_INTERNAL');
        $sourceCount = count(array_unique(array_column($steps, 'source')));
        $score = 80;
        if ($internal) {
            $score += 10;
        }
        if ($sourceCount > 1) {
            $score -= 5;
        }
        foreach ($steps as $step) {
            if (($step['source_type'] ?? '') !== 'INTERNAL_ORDER_BOOK' && ($step['source_type'] ?? '') !== 'EXTERNAL_EXECUTABLE') {
                $score -= 30;
            }
        }

        return (string) max(0, min(100, $score));
    }
}
