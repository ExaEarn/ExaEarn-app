<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\FarmingProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgriProjectStateService
{
    private const TRANSITIONS = [
        'DRAFT' => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['UNDER_REVIEW', 'CANCELLED'],
        'UNDER_REVIEW' => ['NEEDS_INFORMATION', 'APPROVED', 'REJECTED', 'SUSPENDED'],
        'NEEDS_INFORMATION' => ['SUBMITTED', 'CANCELLED'],
        'APPROVED' => ['SCHEDULED', 'OPEN', 'SUSPENDED'],
        'SCHEDULED' => ['OPEN', 'SUSPENDED', 'CANCELLED'],
        'OPEN' => ['FULLY_FUNDED', 'PAUSED', 'SUSPENDED', 'CANCELLED', 'REFUNDING'],
        'FULLY_FUNDED' => ['ACTIVE', 'REFUNDING', 'SUSPENDED'],
        'ACTIVE' => ['PLANTING', 'GROWING', 'HARVEST_PENDING', 'FAILED', 'SUSPENDED'],
        'PLANTING' => ['GROWING', 'FAILED', 'SUSPENDED'],
        'GROWING' => ['HARVEST_PENDING', 'FAILED', 'SUSPENDED'],
        'HARVEST_PENDING' => ['HARVESTED', 'FAILED', 'MANUAL_REVIEW'],
        'HARVESTED' => ['SETTLEMENT_PENDING', 'MANUAL_REVIEW'],
        'SETTLEMENT_PENDING' => ['COMPLETED', 'MANUAL_REVIEW'],
        'FAILED' => ['REFUNDING', 'MANUAL_REVIEW'],
        'REFUNDING' => ['REFUNDED', 'MANUAL_REVIEW'],
        'PAUSED' => ['OPEN', 'SUSPENDED', 'CANCELLED'],
        'SUSPENDED' => ['UNDER_REVIEW', 'CANCELLED', 'REFUNDING'],
        'MANUAL_REVIEW' => ['SETTLEMENT_PENDING', 'REFUNDING', 'SUSPENDED'],
    ];

    public function transition(User $actor, int $projectId, string $next, string $reason): FarmingProject
    {
        if ($actor->role !== 'admin') throw new RuntimeException('Authorized AgriTech reviewer access is required.');
        return DB::transaction(function () use ($actor, $next, $projectId, $reason): FarmingProject {
            $project = FarmingProject::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
            $current = strtoupper((string) $project->status);
            $next = strtoupper($next);
            if (!in_array($next, self::TRANSITIONS[$current] ?? [], true)) {
                throw new RuntimeException("Invalid project transition from {$current} to {$next}.");
            }
            if (in_array($next, ['APPROVED', 'OPEN'], true) && $project->verification_status !== 'VERIFIED') {
                throw new RuntimeException('Verified farm, land and project evidence is required.');
            }
            if ($next === 'OPEN' && in_array($project->economic_type, ['INVESTMENT', 'REVENUE_SHARE', 'TOKENIZED_INVESTMENT'], true) && $project->legal_status !== 'APPROVED') {
                throw new RuntimeException('Legal product approval is required before public funding.');
            }
            $project->status = $next;
            $project->metadata = array_merge($project->metadata ?? [], ['last_transition' => [
                'from' => $current, 'to' => $next, 'reason' => $reason, 'actor_id' => $actor->id, 'at' => now()->toIso8601String(),
            ]]);
            $project->save();
            return $project;
        });
    }
}
