<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Services\FinancialDecimal;

class ConsolidatedLiquidityBookService
{
    public function __construct(
        private readonly NormalizedMarketDataService $marketData,
        private readonly LiquiditySourceRegistry $sources,
    ) {
    }

    public function build(string $symbol, int $limit = 20, bool $includeReference = true): array
    {
        $books = [$this->marketData->internalBook($symbol, $limit)];
        if ($includeReference) {
            foreach (array_keys($this->sources->adapters()) as $source) {
                $books[] = $this->marketData->externalBook($source, $symbol, $limit);
            }
        }

        $bids = [];
        $asks = [];
        foreach ($books as $book) {
            foreach ($book['bids'] as $level) {
                $bids[] = $this->level($book, 'buy', $level);
            }
            foreach ($book['asks'] as $level) {
                $asks[] = $this->level($book, 'sell', $level);
            }
        }

        usort($bids, fn (array $a, array $b): int => FinancialDecimal::compare($b['price'], $a['price']));
        usort($asks, fn (array $a, array $b): int => FinancialDecimal::compare($a['price'], $b['price']));

        return [
            'symbol' => strtoupper($symbol),
            'bids' => array_slice($bids, 0, $limit),
            'asks' => array_slice($asks, 0, $limit),
            'sources' => $books,
            'built_at' => now()->toISOString(),
        ];
    }

    private function level(array $book, string $side, array $level): array
    {
        return [
            'source' => $book['source'],
            'source_type' => $book['source_type'],
            'venue' => $book['venue'],
            'side' => $side,
            'price' => $level['price'],
            'quantity' => $level['quantity'],
            'fee_bps' => (string) data_get($book, 'fees.taker_bps', '0'),
            'latency_ms' => (int) ($book['latency_ms'] ?? 0),
            'executable' => (bool) ($book['executable'] ?? false),
        ];
    }
}
