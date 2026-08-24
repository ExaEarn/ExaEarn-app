<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiDecision;
use App\Models\ExaAiSurveillanceCase;
use Illuminate\Support\Str;

class ExaAiSurveillanceService
{
    public function scanUser(int $userId): array
    {
        $cases = [];

        $rapidRejected = ExaAiDecision::query()
            ->where('user_id', $userId)
            ->where('risk_decision', 'rejected')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($rapidRejected >= 5) {
            $cases[] = ExaAiSurveillanceCase::query()->firstOrCreate([
                'user_id' => $userId,
                'signal_type' => 'REPEATED_RISK_REJECTIONS',
                'status' => 'open',
            ], [
                'case_uuid' => (string) Str::uuid(),
                'severity' => 'medium',
                'evidence' => [
                    'rejected_decisions_last_hour' => $rapidRejected,
                    'action' => 'review_strategy_configuration',
                ],
            ]);
        }

        return $cases;
    }
}
