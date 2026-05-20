<?php

declare(strict_types=1);

namespace App\Services;

class OrderSplittingService
{
    public function split(string $side, string $quantity, array $sources): array
    {
        $remaining = (float) $quantity;
        $plan = [];

        usort($sources, function ($a, $b) use ($side): int {
            $key = $side === 'buy' ? 'best_ask' : 'best_bid';
            return $side === 'buy'
                ? ((float) $a[$key] <=> (float) $b[$key])
                : ((float) $b[$key] <=> (float) $a[$key]);
        });

        foreach ($sources as $src) {
            if ($remaining <= 0) {
                break;
            }

            $depth = max((int) ($src['liquidity_depth'] ?? 0), 1);
            $alloc = min($remaining, max($remaining * 0.4, $remaining / max(count($sources), 1)));
            if ($depth < 5) {
                $alloc *= 0.6;
            }

            $alloc = max(0, $alloc);
            if ($alloc > 0) {
                $plan[] = [
                    'source' => $src['source'],
                    'quantity' => round($alloc, 8),
                    'price' => (float) ($side === 'buy' ? ($src['best_ask'] ?? 0) : ($src['best_bid'] ?? 0)),
                ];
                $remaining -= $alloc;
            }
        }

        if ($remaining > 0 && $plan !== []) {
            $plan[0]['quantity'] = round($plan[0]['quantity'] + $remaining, 8);
        }

        return $plan;
    }
}
