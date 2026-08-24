<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

interface LiquiditySourceInterface
{
    public function code(): string;

    public function capabilities(): array;

    public function state(): string;

    public function healthCheck(): array;

    public function getOrderBook(string $symbol, int $limit = 20): array;

    public function estimateExecution(string $symbol, string $side, string $quantity): array;

    public function placeOrder(array $order): array;
}
