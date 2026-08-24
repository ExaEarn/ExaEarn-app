<?php

declare(strict_types=1);

namespace App\Services\Spot;

interface ExternalSpotVenue
{
    public function venueCode(): string;

    public function healthCheck(): array;

    public function getMarkets(): array;

    public function getTicker(string $symbol): array;

    public function getOrderBook(string $symbol, int $limit = 20): array;

    public function getBalance(string $asset): array;

    public function placeOrder(array $order): array;

    public function cancelOrder(string $symbol, string $externalOrderId): array;

    public function getOrder(string $symbol, string $externalOrderId): array;

    public function getTrades(string $symbol, string $externalOrderId): array;
}
