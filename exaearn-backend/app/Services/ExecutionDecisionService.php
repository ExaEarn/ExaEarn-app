<?php

declare(strict_types=1);

namespace App\Services;

class ExecutionDecisionService
{
    public function decide(string $side, string $quantity, array $sources, OrderSplittingService $splitter): array
    {
        $best = $this->bestSource($side, $sources);
        if (!$best) {
            return ['mode' => 'none', 'plan' => []];
        }

        $second = $this->secondBestSource($side, $sources);
        if (!$second) {
            return ['mode' => 'single', 'plan' => [[
                'source' => $best['source'],
                'quantity' => (float) $quantity,
                'price' => (float) ($side === 'buy' ? $best['best_ask'] : $best['best_bid']),
            ]]];
        }

        $bestPrice = (float) ($side === 'buy' ? $best['best_ask'] : $best['best_bid']);
        $secondPrice = (float) ($side === 'buy' ? $second['best_ask'] : $second['best_bid']);
        $priceGapPct = $bestPrice > 0 ? abs(($secondPrice - $bestPrice) / $bestPrice) * 100 : 100;

        if ($priceGapPct < 0.15) {
            return ['mode' => 'split', 'plan' => $splitter->split($side, $quantity, [$best, $second])];
        }

        return ['mode' => 'single', 'plan' => [[
            'source' => $best['source'],
            'quantity' => (float) $quantity,
            'price' => $bestPrice,
        ]]];
    }

    private function bestSource(string $side, array $sources): ?array
    {
        $valid = array_filter($sources, fn ($s) => ((float) ($side === 'buy' ? $s['best_ask'] : $s['best_bid'])) > 0);
        if ($valid === []) {
            return null;
        }

        usort($valid, function ($a, $b) use ($side): int {
            $key = $side === 'buy' ? 'best_ask' : 'best_bid';
            return $side === 'buy' ? ((float) $a[$key] <=> (float) $b[$key]) : ((float) $b[$key] <=> (float) $a[$key]);
        });

        return $valid[0] ?? null;
    }

    private function secondBestSource(string $side, array $sources): ?array
    {
        $valid = array_filter($sources, fn ($s) => ((float) ($side === 'buy' ? $s['best_ask'] : $s['best_bid'])) > 0);
        if (count($valid) < 2) {
            return null;
        }

        usort($valid, function ($a, $b) use ($side): int {
            $key = $side === 'buy' ? 'best_ask' : 'best_bid';
            return $side === 'buy' ? ((float) $a[$key] <=> (float) $b[$key]) : ((float) $b[$key] <=> (float) $a[$key]);
        });

        return array_values($valid)[1] ?? null;
    }
}
