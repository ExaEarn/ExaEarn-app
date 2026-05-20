<?php

namespace App\Services;

class CrossChainExecutionService
{
    protected $bridgeService;

    public function __construct(BridgeService $bridgeService)
    {
        $this->bridgeService = $bridgeService;
    }

    /**
     * Execute a cross-chain trade.
     * 
     * @param array $order
     * @param array $route
     * @return array
     */
    public function execute(array $order, array $route): array
    {
        if ($route['same_chain']) {
            return ['status' => 'executed_locally'];
        }

        // Bridge assets first
        return $this->bridgeService->initiateBridgeTransfer(
            $order['asset'],
            $order['amount'],
            $order['from_chain'],
            $route['to_chain']
        );
    }
}
