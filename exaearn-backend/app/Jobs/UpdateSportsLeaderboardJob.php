<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SportsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateSportsLeaderboardJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ?int $competitionId = null,
        public readonly ?int $athleteId = null,
    ) {
    }

    public function handle(SportsService $sportsService): void
    {
        $sportsService->refreshAthleteLeaderboard($this->competitionId, $this->athleteId);
    }
}
