<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FuturesPosition;

class FuturesPositionService
{
    private const SCALE = 8;

    public function __construct(private readonly FuturesMarginService $marginService)
    {
    }

    public function refreshUnrealizedPnl(FuturesPosition $position, string $markPrice): FuturesPosition
    {
        $quantity = (string) $position->quantity;
        $market = $position->market()->firstOrFail();
        $margin = ($position->margin_type ?? 'cross') === 'isolated'
            ? (string) ($position->isolated_margin ?: $position->margin)
            : (string) $position->margin;
        $unrealized = $this->marginService->unrealizedPnl((string) $position->side, (string) $position->entry_price, $markPrice, $quantity);
        $notional = $this->marginService->notional($markPrice, $quantity);
        $maintenanceMargin = $this->marginService->maintenanceMargin($market, $notional);
        $liquidation = $this->marginService->liquidationPrice((string) $position->side, (string) $position->entry_price, $quantity, $margin, $maintenanceMargin);
        $bankruptcy = $this->marginService->bankruptcyPrice((string) $position->side, (string) $position->entry_price, $quantity, $margin);

        $position->mark_price = $markPrice;
        $position->unrealized_pnl = $unrealized;
        $position->maintenance_margin = $maintenanceMargin;
        $position->liquidation_price = $liquidation;
        $position->bankruptcy_price = $bankruptcy;
        $position->save();

        return $position;
    }
}
