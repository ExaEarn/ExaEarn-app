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
        $startedAt = hrtime(true);
        $bots = MarketMakerBot::query()->limit($botCount)->get();
        $decisionLatencies = [];

        foreach ($bots as $bot) {
            for ($cycle = 0; $cycle < $cyclesPerBot; $cycle++) {
                $cycleStartedAt = hrtime(true);
                $this->quotes->plan($bot);
                $decisionLatencies[] = max(0, (int) round((hrtime(true) - $cycleStartedAt) / 1_000_000));
            }
        }

        $elapsedMs = max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000));
        sort($decisionLatencies, SORT_NUMERIC);
        $decisionCount = count($decisionLatencies);
        $p50Index = max(0, (int) ceil($decisionCount * 0.50) - 1);
        $p95Index = max(0, (int) ceil($decisionCount * 0.95) - 1);

        return MarketMakerBotLoadRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'scenario' => 'quote_storm_probe',
            'bot_count' => $bots->count(),
            'cycles_per_bot' => $cyclesPerBot,
            'status' => 'PASS',
            'metrics' => [
                'decisions' => $decisionCount,
                'errors' => 0,
                'elapsed_ms' => $elapsedMs,
                'p50_decision_latency_ms' => $decisionLatencies === [] ? 0 : $decisionLatencies[$p50Index],
                'p95_decision_latency_ms' => $decisionLatencies === [] ? 0 : $decisionLatencies[$p95Index],
                'retail_fairness_probe' => 'non_blocking_calculation_only',
            ],
            'metadata' => ['capacity_claim' => 'local_software_probe_not_exchange_scale'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
