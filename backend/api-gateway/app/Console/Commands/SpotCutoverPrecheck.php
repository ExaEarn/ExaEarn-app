<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Spot\SpotCutoverReadinessService;
use Illuminate\Console\Command;

class SpotCutoverPrecheck extends Command
{
    protected $signature = 'spot:cutover-precheck {market}';

    protected $description = 'Run Spot per-market cutover readiness checks.';

    public function handle(SpotCutoverReadinessService $readiness): int
    {
        $result = $readiness->evaluate((string) $this->argument('market'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
