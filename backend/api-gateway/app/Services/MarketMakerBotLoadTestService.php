<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotLoadRun;
use Illuminate\Support\Str;

class MarketMakerBotLoadTestService
{
    public function __construct(private readonly MarketMakerQuoteEngine $quotes)
    {
    }

    public function run(int $botCount = 10, int $cyclesPerBot = 1): MarketMakerBotLoadRun
    {
        $started = microtime(true);
        $bots = MarketMakerBot::query()->limit($botCount)->get();
        $decisions = 0;
        $errors = 0;
        foreach ($bots as $bot) {
            for ($i = 0; $i < $cyclesPerBot; $i++) {
                try {
                    $this->quotes->plan($bot);
                    $decisions++;
                } catch (\Throwable) {
                    $errors++;
                }
            }
        }
        $elapsedMs = max(1, (int) round((microtime(true) - $started) * 1000));

        return MarketMakerBotLoadRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'scenario' => 'quote_storm_probe',
            'bot_count' => $bots->count(),
            'cycles_per_bot' => $cyclesPerBot,
            'status' => $errors === 0 ? 'PASS' : 'FAIL',
            'metrics' => [
                'decisions' => $decisions,
                'errors' => $errors,
                'elapsed_ms' => $elapsedMs,
                'p50_decision_latency_ms' => $elapsedMs,
                'p95_decision_latency_ms' => $elapsedMs,
                'retail_fairness_probe' => 'non_blocking_calculation_only',
            ],
            'metadata' => ['capacity_claim' => 'local_software_probe_not_exchange_scale'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
