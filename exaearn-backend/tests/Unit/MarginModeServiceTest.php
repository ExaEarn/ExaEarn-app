<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FuturesPosition;
use App\Services\MarginModeService;
use Tests\TestCase;

class MarginModeServiceTest extends TestCase
{
    public function test_calculate_margin_cross_uses_wallet_balance(): void
    {
        $service = app(MarginModeService::class);
        $position = new FuturesPosition([
            'margin_type' => 'cross',
            'margin' => '100.00000000',
            'entry_price' => '50000.00000000',
            'quantity' => '1.00000000',
            'side' => 'long',
            'liquidation_price' => '45000.00000000',
        ]);

        $effective = $service->calculateMargin($position, '50.00000000');

        $this->assertSame('150.00000000', $effective);
    }

    public function test_calculate_margin_isolated_keeps_position_margin(): void
    {
        $service = app(MarginModeService::class);
        $position = new FuturesPosition([
            'margin_type' => 'isolated',
            'margin' => '100.00000000',
            'entry_price' => '50000.00000000',
            'quantity' => '1.00000000',
            'side' => 'long',
            'liquidation_price' => '45000.00000000',
        ]);

        $effective = $service->calculateMargin($position, '9999.00000000');

        $this->assertSame('100.00000000', $effective);
    }
}
