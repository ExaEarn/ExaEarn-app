<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Market;
use App\Services\Spot\SpotCutoverService;
use Illuminate\Console\Command;

class SpotCutoverControl extends Command
{
    protected $signature = 'spot:cutover {market} {action : prepare|promote|rollback|shadow|legacy} {--reason=operator_control}';

    protected $description = 'Run controlled Spot cutover state transitions for one market.';

    public function handle(SpotCutoverService $cutover): int
    {
        $market = Market::query()->where('symbol', strtoupper(str_replace('-', '/', (string) $this->argument('market'))))->firstOrFail();
        $action = strtolower((string) $this->argument('action'));
        $reason = (string) $this->option('reason');

        $result = match ($action) {
            'prepare' => $cutover->prepareCutover($market, $reason),
            'promote' => ['journal' => $cutover->promote($market->fresh(), $reason)->toArray()],
            'rollback' => $cutover->rollback($market->fresh(), $reason),
            'shadow' => ['journal' => $cutover->transition($market, 'SHADOW', $reason)->toArray()],
            'legacy' => ['journal' => $cutover->transition($market, 'LEGACY', $reason)->toArray()],
            default => throw new \RuntimeException('Unsupported cutover action.'),
        };

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
