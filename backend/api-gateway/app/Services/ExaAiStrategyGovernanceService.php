<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiStrategyTransition;
use App\Models\ExaAiStrategyVersion;
use RuntimeException;

class ExaAiStrategyGovernanceService
{
    private const STATES = [
        'DRAFT',
        'BACKTESTING',
        'SHADOW',
        'RISK_REVIEW',
        'APPROVED',
        'LIMITED_PRODUCTION',
        'PRODUCTION',
        'RESTRICTED',
        'PAUSED',
        'RETIRED',
    ];

    public function transition(ExaAiStrategyVersion $version, string $newState, string $reason, ?int $actorId = null): ExaAiStrategyVersion
    {
        $newState = strtoupper($newState);
        if (! in_array($newState, self::STATES, true)) {
            throw new RuntimeException('Unsupported ExaAI strategy lifecycle state.');
        }

        $previous = strtoupper((string) ($version->state ?: 'DRAFT'));
        $version->forceFill([
            'state' => strtolower($newState),
            'activated_at' => in_array($newState, ['LIMITED_PRODUCTION', 'PRODUCTION'], true) ? now() : $version->activated_at,
            'retired_at' => $newState === 'RETIRED' ? now() : $version->retired_at,
        ])->save();

        ExaAiStrategyTransition::query()->create([
            'strategy_version_id' => $version->id,
            'actor_id' => $actorId,
            'previous_state' => $previous,
            'new_state' => $newState,
            'reason' => $reason,
            'metadata' => ['source' => 'exaai_operations'],
            'transitioned_at' => now(),
        ]);

        return $version->fresh();
    }
}
