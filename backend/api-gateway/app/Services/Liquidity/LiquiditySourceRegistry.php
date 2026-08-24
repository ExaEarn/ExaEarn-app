<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\LiquiditySource;
use Illuminate\Support\Str;

class LiquiditySourceRegistry
{
    public function __construct(private readonly BinanceLiquidityAdapter $binance)
    {
    }

    /**
     * @return array<string,LiquiditySourceInterface>
     */
    public function adapters(): array
    {
        return [
            $this->binance->code() => $this->binance,
        ];
    }

    public function syncConfiguredSources(): array
    {
        $sources = [];
        foreach ($this->adapters() as $code => $adapter) {
            $source = LiquiditySource::query()->updateOrCreate(
                ['code' => $code],
                [
                    'source_id' => (string) (LiquiditySource::query()->where('code', $code)->value('source_id') ?: Str::uuid()),
                    'name' => $code,
                    'type' => 'EXTERNAL_VENUE',
                    'state' => $adapter->state(),
                    'capabilities' => $adapter->capabilities(),
                    'configuration' => ['secret_storage' => 'environment_reference', 'withdrawals_enabled' => false],
                    'last_health_at' => now(),
                ]
            );
            $sources[] = $source->fresh();
        }

        return $sources;
    }

    public function adapter(string $code): LiquiditySourceInterface
    {
        return $this->adapters()[strtoupper($code)] ?? throw new \RuntimeException("Liquidity source {$code} is not registered.");
    }
}
