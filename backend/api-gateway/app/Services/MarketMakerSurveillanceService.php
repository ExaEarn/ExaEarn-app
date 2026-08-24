<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerSurveillanceCase;
use Illuminate\Support\Str;

class MarketMakerSurveillanceService
{
    public function detectRelatedInstitutionMarketOverlap(MarketMakerProfile $profile, string $marketSymbol): ?MarketMakerSurveillanceCase
    {
        $symbol = strtoupper($marketSymbol);
        $related = MarketMakerProfile::query()
            ->where('institution_id', $profile->institution_id)
            ->where('id', '!=', $profile->id)
            ->whereHas('assignments', fn ($query) => $query->where('market_symbol', $symbol)->where('status', 'ACTIVE'))
            ->first();

        if (! $related) {
            return null;
        }

        return MarketMakerSurveillanceCase::query()->firstOrCreate(
            [
                'market_maker_id' => $profile->id,
                'signal_type' => 'RELATED_ACCOUNT_MARKET_OVERLAP',
                'status' => 'OPEN',
            ],
            [
                'case_uuid' => (string) Str::uuid(),
                'institution_id' => $profile->institution_id,
                'severity' => 'HIGH',
                'evidence' => [
                    'market_symbol' => $symbol,
                    'related_market_maker_id' => $related->id,
                    'assignment_count' => MarketMakerMarketAssignment::query()->where('market_symbol', $symbol)->where('status', 'ACTIVE')->count(),
                ],
            ]
        );
    }
}
