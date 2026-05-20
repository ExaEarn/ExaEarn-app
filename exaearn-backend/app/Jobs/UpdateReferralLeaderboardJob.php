<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ReferralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateReferralLeaderboardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(ReferralService $referralService): void
    {
        foreach (['weekly', 'monthly', 'all_time'] as $timeframe) {
            $referralService->recalculateLeaderboard($this->userId, $timeframe);
        }
    }
}
