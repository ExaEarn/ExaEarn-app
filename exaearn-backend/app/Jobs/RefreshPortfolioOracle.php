<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PriceOracleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshPortfolioOracle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PriceOracleService $oracleService): void
    {
        Log::info('Starting portfolio oracle refresh job');
        $oracleService->refreshMarketData();
        Log::info('Portfolio oracle refresh job completed');
    }
}
