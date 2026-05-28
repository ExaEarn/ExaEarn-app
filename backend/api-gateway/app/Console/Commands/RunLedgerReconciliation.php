<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReconciliationService;
use Illuminate\Console\Command;

class RunLedgerReconciliation extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Run ledger reconciliation against treasury totals';

    public function handle(ReconciliationService $reconciliationService): int
    {
        $report = $reconciliationService->reconcileTreasuryAgainstLedger();

        foreach ($report as $row) {
            $status = $row['matched'] ? 'OK' : 'MISMATCH';
            $this->line("{$row['asset']}: {$status} | users={$row['user_total']} treasury={$row['treasury_total']}");
        }

        return self::SUCCESS;
    }
}
