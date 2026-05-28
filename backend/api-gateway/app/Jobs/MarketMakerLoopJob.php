<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MarketMakerConfig;
use App\Services\AntiManipulationService;
use App\Services\MarketMakerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarketMakerLoopJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onConnection('redis');
        $this->onQueue('market-maker');
    }

    public function handle(MarketMakerService $maker, AntiManipulationService $antiManipulation): void
    {
        if (!config('market_maker.enabled', true)) {
            return;
        }

        $configs = MarketMakerConfig::query()->where('status', 'active')->get();
        foreach ($configs as $cfg) {
            $maker->runForSymbol($cfg->symbol);
            $antiManipulation->scan($cfg->symbol);
        }
    }
}
