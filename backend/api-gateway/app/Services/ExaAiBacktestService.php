<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiBacktest;
use App\Models\ExaAiStrategyVersion;
use Illuminate\Support\Str;

class ExaAiBacktestService
{
    public function record(ExaAiStrategyVersion $version, array $payload): ExaAiBacktest
    {
        return ExaAiBacktest::query()->create([
            'backtest_uuid' => (string) Str::uuid(),
            'strategy_definition_id' => $version->strategy_definition_id,
            'strategy_version_id' => $version->id,
            'dataset_reference' => (string) $payload['dataset_reference'],
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'parameters' => $payload['parameters'] ?? [],
            'assumptions' => $payload['assumptions'] ?? [],
            'results' => $payload['results'] ?? [],
            'status' => (string) ($payload['status'] ?? 'completed'),
        ]);
    }
}
