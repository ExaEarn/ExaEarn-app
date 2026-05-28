<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Competition;
use App\Services\RewardEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DistributeCompetitionRewardsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $competitionId)
    {
    }

    public function handle(RewardEngineService $rewardEngine): void
    {
        $competition = Competition::query()
            ->with(['participants.athlete.user'])
            ->findOrFail($this->competitionId);

        $rewardPool = (string) $competition->reward_pool;
        foreach ((array) config('sports.reward_distribution', []) as $rank => $share) {
            $participant = $competition->participants->firstWhere('ranking', (int) $rank);
            if (!$participant || !$participant->athlete || !$participant->athlete->user) {
                continue;
            }

            $amount = function_exists('bcmul')
                ? bcmul($rewardPool, (string) $share, 8)
                : number_format((float) $rewardPool * (float) $share, 8, '.', '');

            $rewardEngine->issueReward(
                (int) $participant->athlete->user->id,
                'sports_competition',
                $amount,
                [
                    'activity_key' => "competition:{$competition->id}:rank:{$rank}",
                    'competition_id' => $competition->id,
                    'ranking' => (int) $rank,
                    'manual_review_required' => $competition->manual_review_required,
                    'reward_amount_override' => $amount,
                ]
            );
        }
    }
}
