<?php

declare(strict_types=1);

namespace App\Services;

class PriceAggregationService
{
    public function aggregate(string $symbol, array $internalBook, array $externalBook): array
    {
        return [
            [
                'source' => 'internal',
                'best_bid' => (string) ($internalBook['bids'][0]['price'] ?? '0'),
                'best_ask' => (string) ($internalBook['asks'][0]['price'] ?? '0'),
                'liquidity_depth' => count($internalBook['bids'] ?? []) + count($internalBook['asks'] ?? []),
                'book' => $internalBook,
            ],
            [
                'source' => 'binance',
                'best_bid' => (string) ($externalBook['bids'][0]['price'] ?? '0'),
                'best_ask' => (string) ($externalBook['asks'][0]['price'] ?? '0'),
                'liquidity_depth' => count($externalBook['bids'] ?? []) + count($externalBook['asks'] ?? []),
                'book' => $externalBook,
            ],
        ];
    }
}
