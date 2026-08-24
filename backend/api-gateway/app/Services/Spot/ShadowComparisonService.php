<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\SpotShadowComparison;
use Illuminate\Support\Str;

class ShadowComparisonService
{
    public function compare(Market $market, array $legacyResult, array $newEngineResult): SpotShadowComparison
    {
        $differences = $this->diff($legacyResult, $newEngineResult);
        $classification = $differences === [] ? 'MATCH' : $this->classify($differences);

        return SpotShadowComparison::query()->create([
            'comparison_id' => (string) Str::uuid(),
            'market_id' => $market->id,
            'market_symbol' => $market->symbol,
            'classification' => $classification,
            'legacy_result' => $legacyResult,
            'new_engine_result' => $newEngineResult,
            'differences' => $differences,
        ]);
    }

    private function diff(array $legacy, array $new): array
    {
        $keys = ['accepted', 'status', 'fills_count', 'best_bid', 'best_ask'];
        $diff = [];
        foreach ($keys as $key) {
            if (($legacy[$key] ?? null) !== ($new[$key] ?? null)) {
                $diff[$key] = ['legacy' => $legacy[$key] ?? null, 'new' => $new[$key] ?? null];
            }
        }

        return $diff;
    }

    private function classify(array $differences): string
    {
        if (array_key_exists('status', $differences)
            && in_array($differences['status']['new'], ['rejected', 'cancelled'], true)) {
            return 'EXPECTED_POLICY_DIFFERENCE';
        }

        return 'UNRESOLVED';
    }
}
