<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CrossChainRoutingService;
use App\Services\MultiChainLiquidityService;
use App\Services\CostOptimizationService;
use App\Services\CrossChainExecutionService;
use App\Services\BridgeService;

class CrossChainRoutingTest extends TestCase
{
    public function test_route_cross_chain_order()
    {
        $routingService = new CrossChainRoutingService(
            new MultiChainLiquidityService(),
            new CostOptimizationService(),
            new CrossChainExecutionService(new BridgeService())
        );

        $order = [
            'asset' => 'USDT',
            'amount' => 100,
            'from_chain' => 'ethereum'
        ];

        $result = $routingService->routeCrossChainOrder($order);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }
}
