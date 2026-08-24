<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiPortfolio;
use RuntimeException;

class ExaAiPositionSizingService
{
    private const SCALE = 8;

    public function size(ExaAiPortfolio $portfolio, array $payload, string $maxExposure): array
    {
        $requested = $this->fmt((string) ($payload['requested_notional'] ?? $payload['target_exposure'] ?? '0'));
        $available = $this->fmt((string) $portfolio->available_amount);
        $referencePrice = $this->fmt((string) ($payload['reference_price'] ?? data_get($payload, 'market_snapshot.last_price', '0')));

        if ($this->compare($requested, '0') <= 0) {
            throw new RuntimeException('ExaAI requested exposure must be greater than zero.');
        }

        if ($this->compare($referencePrice, '0') <= 0) {
            throw new RuntimeException('ExaAI reference price must be greater than zero.');
        }

        $maxPositionPct = $this->fmt((string) data_get($portfolio->limits, 'max_position_pct', '1'));
        $portfolioCap = bcmul((string) $portfolio->allocated_amount, $maxPositionPct, self::SCALE);
        $approved = $this->min($requested, $available);
        $approved = $this->min($approved, $portfolioCap);

        if ($this->compare($maxExposure, '0') > 0) {
            $approved = $this->min($approved, $this->fmt($maxExposure));
        }

        return [
            'requested_notional' => $requested,
            'approved_notional' => $approved,
            'quantity' => bcdiv($approved, $referencePrice, self::SCALE),
            'reference_price' => $referencePrice,
            'available_before' => $available,
            'portfolio_cap' => $portfolioCap,
        ];
    }

    private function min(string $left, string $right): string
    {
        return $this->compare($left, $right) <= 0 ? $left : $right;
    }

    private function fmt(string $value): string
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('BCMath is required for ExaAI financial calculations.');
        }

        return bcadd(trim($value), '0', self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }
}
