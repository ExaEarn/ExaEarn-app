<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Spot\SettlementOutboxService;
use Illuminate\Console\Command;

class ProcessSpotSettlementOutbox extends Command
{
    protected $signature = 'spot:settlement-outbox {--limit=100}';

    protected $description = 'Process pending Spot settlement outbox events idempotently.';

    public function handle(SettlementOutboxService $outbox): int
    {
        $result = $outbox->process((int) $this->option('limit'));
        $metrics = $outbox->metrics();

        $this->line(json_encode(['result' => $result, 'metrics' => $metrics], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
