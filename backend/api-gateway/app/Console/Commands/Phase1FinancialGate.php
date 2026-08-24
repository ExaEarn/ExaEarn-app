<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Reservation;
use App\Services\BalanceProjectionService;
use App\Services\FinancialDecimal;
use App\Services\LedgerReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class Phase1FinancialGate extends Command
{
    protected $signature = 'financial:phase1-gate';

    protected $description = 'Run the Phase 1 financial-core readiness gate before matching-engine migration.';

    public function handle(
        BalanceProjectionService $projections,
        LedgerReconciliationService $reconciliation,
    ): int {
        $report = [
            'generated_at' => now()->toISOString(),
            'database' => [
                'driver' => config('database.default'),
                'connected' => false,
                'canonical_migration_applied' => false,
            ],
            'decimal' => ['bcmath_available' => false],
            'reconciliation' => [],
            'concurrency' => [],
            'ready' => false,
        ];

        try {
            DB::connection()->getPdo();
            $report['database']['connected'] = true;
            $report['database']['canonical_migration_applied'] = DB::table('migrations')
                ->where('migration', '2026_08_14_000001_create_canonical_financial_core_tables')
                ->exists();

            FinancialDecimal::ensureAvailable();
            $report['decimal']['bcmath_available'] = true;

            $report['reconciliation'] = $reconciliation->run();
            $report['concurrency'] = $this->runConcurrentReservationProbe($projections);

            $blockingReconciliation = collect($report['reconciliation'])
                ->except(['legacy_projection_mismatches', 'generated_at'])
                ->filter(fn ($value) => is_array($value) && count($value) > 0)
                ->isNotEmpty();

            $report['ready'] = $report['database']['driver'] === 'pgsql'
                && $report['database']['connected']
                && $report['database']['canonical_migration_applied']
                && $report['decimal']['bcmath_available']
                && !$blockingReconciliation
                && ($report['concurrency']['passed'] ?? false) === true;
        } catch (Throwable $exception) {
            $report['error'] = $exception->getMessage();
        }

        $path = storage_path('app/phase1/phase1-financial-gate.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line('Phase 1 financial gate report: ' . $path);
        $this->line('Database: ' . ($report['database']['connected'] ? 'PASS' : 'FAIL') . ' (' . $report['database']['driver'] . ')');
        $this->line('Canonical migration: ' . ($report['database']['canonical_migration_applied'] ? 'PASS' : 'FAIL'));
        $this->line('BCMath: ' . ($report['decimal']['bcmath_available'] ? 'PASS' : 'FAIL'));
        $this->line('Concurrent reservations: ' . (($report['concurrency']['passed'] ?? false) ? 'PASS' : 'FAIL'));
        $this->line('Blocking reconciliation findings: ' . ($report['ready'] ? 'NONE' : 'CHECK REPORT'));
        $this->line('SAFE TO BEGIN PHASE 2: ' . ($report['ready'] ? 'YES' : 'NO'));

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }

    private function runConcurrentReservationProbe(BalanceProjectionService $projections): array
    {
        $reference = 'PHASE1-GATE-' . now()->format('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $account = Account::query()->create([
            'owner_type' => 'system',
            'owner_id' => null,
            'user_id' => null,
            'account_type' => 'phase1_gate',
            'asset' => 'USDT',
            'balance' => '1000.000000000000000000',
            'status' => 'active',
            'metadata' => ['reference' => $reference],
        ]);

        $barrier = storage_path("app/phase1/{$reference}.go");
        File::ensureDirectoryExists(dirname($barrier));
        $artisan = base_path('artisan');
        $php = PHP_BINARY;

        $processes = [
            new Process([$php, $artisan, 'financial:phase1-reserve-worker', (string) $account->id, 'USDT', '800', $reference . '-A', $barrier], base_path()),
            new Process([$php, $artisan, 'financial:phase1-reserve-worker', (string) $account->id, 'USDT', '800', $reference . '-B', $barrier], base_path()),
        ];

        foreach ($processes as $process) {
            $process->setTimeout(30);
            $process->start();
        }

        File::put($barrier, 'go');

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $line = trim($process->getOutput());
            $decoded = json_decode($line, true);
            $results[] = is_array($decoded)
                ? $decoded + ['exit_code' => $process->getExitCode()]
                : ['ok' => false, 'error' => $line ?: trim($process->getErrorOutput()), 'exit_code' => $process->getExitCode()];
        }

        $projection = $projections->accountProjection($account->fresh());
        $successes = collect($results)->where('ok', true)->count();
        $failures = collect($results)->where('ok', false)->count();
        $activeReserved = Reservation::query()
            ->where('account_id', $account->id)
            ->whereIn('status', [Reservation::STATUS_ACTIVE, Reservation::STATUS_PARTIALLY_CONSUMED])
            ->sum('remaining_amount');

        return [
            'reference' => $reference,
            'account_id' => $account->id,
            'workers' => $results,
            'successes' => $successes,
            'failures' => $failures,
            'reserved' => FinancialDecimal::normalize((string) $activeReserved),
            'available_after' => $projection['available'],
            'passed' => $successes === 1
                && $failures === 1
                && FinancialDecimal::compare((string) $activeReserved, '800') === 0
                && FinancialDecimal::compare($projection['available'], '200') === 0,
        ];
    }
}
