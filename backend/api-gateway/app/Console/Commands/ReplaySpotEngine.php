<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Market;
use App\Services\Spot\MatchingEngineReplayService;
use Illuminate\Console\Command;

class ReplaySpotEngine extends Command
{
    protected $signature = 'spot:replay {market}';

    protected $description = 'Replay Spot engine journal for a market and verify sequence continuity.';

    public function handle(MatchingEngineReplayService $replay): int
    {
        $market = Market::query()->where('symbol', strtoupper((string) $this->argument('market')))->firstOrFail();
        $state = $replay->replay($market);
        $this->line(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
