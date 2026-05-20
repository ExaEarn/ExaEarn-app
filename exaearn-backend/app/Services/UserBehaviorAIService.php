<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AIDecisionLog;
use App\Models\FuturesTrade;

class UserBehaviorAIService
{
    public function analyze(int $userId): array
    {
        $trades = FuturesTrade::query()->where('user_id', $userId)->latest()->limit(100)->get();
        $count = $trades->count();
        if ($count === 0) {
            return ['risk_appetite' => 'unknown', 'recommended_leverage' => 2, 'win_ratio' => 0];
        }

        $wins = $trades->where('realized_pnl', '>', 0)->count();
        $losses = $trades->where('realized_pnl', '<', 0)->count();
        $winRatio = $count > 0 ? round(($wins / $count) * 100, 2) : 0;

        $riskAppetite = $losses > $wins ? 'high' : 'medium';
        $recommendedLeverage = $riskAppetite === 'high' ? 3 : 5;

        AIDecisionLog::query()->create([
            'symbol' => 'USER:' . $userId,
            'decision_type' => 'user_behavior_analysis',
            'inputs' => ['trades' => $count, 'wins' => $wins, 'losses' => $losses],
            'outputs' => ['risk_appetite' => $riskAppetite, 'recommended_leverage' => $recommendedLeverage, 'win_ratio' => $winRatio],
            'safety_applied' => ['human_control' => true],
            'manual_override' => false,
            'decided_at' => now(),
        ]);

        return [
            'risk_appetite' => $riskAppetite,
            'recommended_leverage' => $recommendedLeverage,
            'win_ratio' => $winRatio,
        ];
    }
}
