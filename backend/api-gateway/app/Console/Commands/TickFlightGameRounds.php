<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FlightGameService;
use Illuminate\Console\Command;
use Throwable;

class TickFlightGameRounds extends Command
{
    protected $signature = 'games:flight-tick';

    protected $description = 'Advance EXA Flight rounds, settle auto cashouts, and publish live round events.';

    public function handle(FlightGameService $flightGame): int
    {
        try {
            $snapshot = $flightGame->tick();
        } catch (Throwable $throwable) {
            report($throwable);
            $this->error('EXA Flight tick failed: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $round = $snapshot['round'] ?? [];
        $this->line(sprintf(
            'Round %s is %s at %sx',
            (string) ($round['round_number'] ?? '-'),
            (string) ($round['status'] ?? 'unknown'),
            (string) ($round['current_multiplier'] ?? '1.00000000')
        ));

        return self::SUCCESS;
    }
}
