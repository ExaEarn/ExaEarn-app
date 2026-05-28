<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesMarket;
use App\Models\FuturesPosition;
use Illuminate\Support\Facades\DB;

class FuturesExecutionService
{
    public function __construct(
        private readonly FuturesPositionService $positionService,
        private readonly ConditionalOrderService $conditionalOrderService,
        private readonly FuturesOrderService $futuresOrderService,
        private readonly FuturesLiquidationService $liquidationService,
    ) {
    }

    public function onMarketTick(string $symbol, string $markPrice): array
    {
        $symbol = strtoupper($symbol);

        return DB::transaction(function () use ($symbol, $markPrice): array {
            $market = FuturesMarket::query()->where('symbol', $symbol)->lockForUpdate()->firstOrFail();
            $market->last_price = $markPrice;
            $market->save();

            $updatedPositions = 0;
            $positions = FuturesPosition::query()
                ->where('symbol', $symbol)
                ->where('status', 'open')
                ->lockForUpdate()
                ->get();

            foreach ($positions as $position) {
                $this->positionService->refreshUnrealizedPnl($position, $markPrice);
                $updatedPositions++;
            }

            $conditionalTriggered = $this->conditionalOrderService->triggerPendingOrders($symbol, $markPrice);
            $advancedTriggered = $this->futuresOrderService->processTriggeredOrders($symbol, $markPrice);

            $liquidated = 0;
            foreach ($positions as $position) {
                $fresh = $position->fresh();
                if ($fresh && $fresh->status === 'open' && $this->liquidationService->shouldLiquidate($fresh)) {
                    $this->liquidationService->liquidate($fresh);
                    $this->conditionalOrderService->cancelByPosition($fresh->id);
                    $liquidated++;
                }
            }

            return [
                'symbol' => $symbol,
                'mark_price' => $markPrice,
                'positions_updated' => $updatedPositions,
                'conditional_triggered' => count($conditionalTriggered),
                'advanced_triggered' => $advancedTriggered,
                'liquidated' => $liquidated,
            ];
        });
    }
}
