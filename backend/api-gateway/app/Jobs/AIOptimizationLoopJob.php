<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AIModelService;
use App\Services\AIRiskDetectionService;
use App\Services\AdaptiveMarketMakerService;
use App\Services\MarketDataCollectorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AIOptimizationLoopJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onConnection('redis');
        $this->onQueue('ai');
    }

    public function handle(
        MarketDataCollectorService $collector,
        AIModelService $model,
        AdaptiveMarketMakerService $marketMaker,
        AIRiskDetectionService $riskDetection,
    ): void {
        if (!config('ai_intel.enabled', true)) {
            return;
        }

        $symbols = array_filter(array_map('trim', (array) config('ai_intel.symbols', [])));

        foreach ($symbols as $symbol) {
            $rows = $collector->recent($symbol, 120);
            $history = $rows->map(fn ($r) => [
                'price' => (float) $r->price,
                'volume' => (float) $r->volume,
                'spread' => (float) $r->spread,
                'volatility' => (float) ($r->volatility ?? 0),
                'timestamp' => $r->timestamp?->toISOString(),
            ])->all();

            $ai = $model->infer($symbol, $history);
            $marketMaker->adjust($symbol, $ai);
            $riskDetection->detectMarketAnomaly($symbol, $history);
        }
    }
}
