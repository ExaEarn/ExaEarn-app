<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

interface ExternalVenueAdapterInterface extends LiquiditySourceInterface
{
    public function getMarkets(): array;

    public function getTicker(string $symbol): array;

    public function cancelOrder(string $symbol, string $clientOrderId): array;

    public function getOrder(string $symbol, string $clientOrderId): array;

    public function getOpenOrders(string $symbol): array;

    public function getTrades(string $symbol, string $clientOrderId): array;

    public function getBalances(): array;

    public function getTradingFees(string $symbol): array;
}
