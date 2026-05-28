<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SolvencyService;
use Illuminate\Console\Command;

class CheckSolvency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'solvency:check {--force : Force fresh check ignoring cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check system solvency and alert if insolvent';

    /**
     * Execute the console command.
     */
    public function handle(SolvencyService $solvencyService): int
    {
        if ($this->option('force')) {
            $solvencyService->clearCache();
            $this->info('Cache cleared, performing fresh solvency check...');
        }

        $solvencyStatus = $solvencyService->checkSolvency();

        $this->info('Solvency Check Results:');
        $this->newLine();

        $allSolvent = true;

        foreach ($solvencyStatus as $currency => $status) {
            $symbol = $status['is_solvent'] ? '✅' : '❌';
            $this->line("{$symbol} {$currency}:");
            $this->line("  User Balances: {$status['total_user_balances']}");
            $this->line("  Treasury Balances: {$status['total_treasury_balances']}");
            $this->line("  Status: " . ($status['is_solvent'] ? 'SOLVENT' : 'INSOLVENT'));

            if (!$status['is_solvent']) {
                $allSolvent = false;
                $this->error("  Deficit: {$status['deficit']}");
            }

            $this->newLine();
        }

        if (!$allSolvent) {
            $this->error('🚨 CRITICAL: System is insolvent!');
            $this->error('Withdrawals have been frozen.');
            $this->error('Immediate administrator attention required.');

            // In the service, this would trigger alerts
            $solvencyService->handleInsolvency($solvencyStatus);

            return Command::FAILURE;
        }

        $this->info('✅ System is solvent. All currencies balanced.');

        return Command::SUCCESS;
    }
}