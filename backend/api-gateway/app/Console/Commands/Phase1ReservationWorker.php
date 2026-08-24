<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;
use Throwable;

class Phase1ReservationWorker extends Command
{
    protected $signature = 'financial:phase1-reserve-worker
        {account_id}
        {asset}
        {amount}
        {idempotency_key}
        {barrier_file}';

    protected $description = 'Internal Phase 1 gate worker used to verify concurrent reservation safety.';

    public function handle(ReservationService $reservations): int
    {
        $barrier = (string) $this->argument('barrier_file');
        $deadline = microtime(true) + 10;

        while (!file_exists($barrier) && microtime(true) < $deadline) {
            usleep(25_000);
        }

        try {
            $reservation = $reservations->reserve(
                (int) $this->argument('account_id'),
                (string) $this->argument('asset'),
                (string) $this->argument('amount'),
                'phase1_gate_concurrency',
                'phase1_gate',
                (string) $this->argument('idempotency_key'),
                (string) $this->argument('idempotency_key'),
                ['source' => 'financial:phase1-reserve-worker'],
            );

            $this->line(json_encode([
                'ok' => true,
                'reservation_id' => $reservation->reservation_id,
                'status' => $reservation->status,
                'remaining_amount' => (string) $reservation->remaining_amount,
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line(json_encode([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }
    }
}
