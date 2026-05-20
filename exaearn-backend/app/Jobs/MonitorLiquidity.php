<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LiquidityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorLiquidity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(LiquidityService $liquidityService): void
    {
        Log::info('Starting liquidity monitoring job');

        // Check for low liquidity
        $lowLiquidityAlerts = $liquidityService->detectLowLiquidity();

        if (!empty($lowLiquidityAlerts)) {
            Log::warning('Low liquidity detected', ['alerts' => $lowLiquidityAlerts]);

            // Log threshold breaches
            foreach ($lowLiquidityAlerts as $alert) {
                $liquidityService->logThresholdBreach(
                    $alert['provider'],
                    $alert['currency'],
                    $alert['current_balance'],
                    $alert['minimum_threshold'],
                    'low'
                );
            }

            // Auto-trigger rebalancing
            $rebalanceActions = $liquidityService->autoTriggerRebalance();

            if (!empty($rebalanceActions)) {
                Log::info('Auto-rebalancing completed', ['actions' => $rebalanceActions]);
            }
        }

        // Check for excess liquidity (high thresholds)
        $liquidityStatus = $liquidityService->getLiquidityStatus();
        $excessAlerts = array_filter($liquidityStatus, fn($status) => $status['status'] === 'excess');

        if (!empty($excessAlerts)) {
            Log::info('Excess liquidity detected', ['alerts' => $excessAlerts]);

            // Log high threshold breaches
            foreach ($excessAlerts as $alert) {
                $liquidityService->logThresholdBreach(
                    $alert['provider'],
                    $alert['currency'],
                    $alert['current_balance'],
                    $alert['maximum_threshold'],
                    'high'
                );
            }
        }

        Log::info('Liquidity monitoring job completed');
    }
}