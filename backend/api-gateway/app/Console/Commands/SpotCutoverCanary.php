<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Market;
use App\Services\Spot\SpotCutoverService;
use Illuminate\Console\Command;

class SpotCutoverCanary extends Command
{
    protected $signature = 'spot:cutover-canary {market} {--amount=1} {--price=10}';

    protected $description = 'Run a controlled Spot canary trade through the NEW engine for one market.';

    public function handle(SpotCutoverService $cutover): int
    {
        $market = Market::query()->where('symbol', strtoupper(str_replace('-', '/', (string) $this->argument('market'))))->firstOrFail();
        $result = $cutover->runCanary($market, (string) $this->option('amount'), (string) $this->option('price'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
