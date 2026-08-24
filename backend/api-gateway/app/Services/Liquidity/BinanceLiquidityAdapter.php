<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Services\ExternalLiquidityProviderService;
use App\Services\FinancialDecimal;
use RuntimeException;

class BinanceLiquidityAdapter implements ExternalVenueAdapterInterface
{
    public function __construct(private readonly ExternalLiquidityProviderService $external)
    {
    }

    public function code(): string
    {
        return 'BINANCE';
    }

    public function capabilities(): array
    {
        return [
            'market_data' => true,
            'authenticated_balances' => (bool) config('liquidity.external_venues.binance.enabled', false),
            'order_placement' => false,
            'fill_ingestion' => false,
            'withdrawals' => false,
        ];
    }

    public function state(): string
    {
        if (! (bool) config('liquidity.external_venues.binance.enabled', false)) {
            return 'UNCONFIGURED';
        }

        return (string) config('liquidity.external_venues.binance.environment', 'TESTING');
    }

    public function healthCheck(): array
    {
        return [
            'venue' => $this->code(),
            'state' => $this->state(),
            'status' => $this->state() === 'LIVE' ? 'READY' : 'NOT_LIVE',
            'capabilities' => $this->capabilities(),
        ];
    }

    public function getMarkets(): array
    {
        return [];
    }

    public function getTicker(string $symbol): array
    {
        return ['venue' => $this->code(), 'symbol' => strtoupper($symbol), 'status' => 'REFERENCE_ONLY'];
    }

    public function getOrderBook(string $symbol, int $limit = 20): array
    {
        $book = $this->external->fetchOrderBook(str_replace('/', '', strtoupper($symbol)), $limit);

        return [
            'venue' => $this->code(),
            'symbol' => strtoupper($symbol),
            'bids' => $book['bids'] ?? [],
            'asks' => $book['asks'] ?? [],
            'timestamp' => now()->toISOString(),
            'executable' => $this->state() === 'LIVE',
            'source_type' => $this->state() === 'LIVE' ? 'EXTERNAL_EXECUTABLE' : 'EXTERNAL_REFERENCE',
        ];
    }

    public function estimateExecution(string $symbol, string $side, string $quantity): array
    {
        $book = $this->getOrderBook($symbol, 50);
        $levels = strtolower($side) === 'buy' ? ($book['asks'] ?? []) : ($book['bids'] ?? []);
        $remaining = FinancialDecimal::normalize($quantity);
        $filled = '0';
        $quote = '0';
        $lastPrice = '0';

        foreach ($levels as $level) {
            if (FinancialDecimal::compare($remaining, '0') <= 0) {
                break;
            }
            $available = FinancialDecimal::normalize((string) ($level['amount'] ?? $level['quantity'] ?? '0'));
            $price = FinancialDecimal::normalize((string) ($level['price'] ?? '0'));
            if (FinancialDecimal::compare($available, '0') <= 0 || FinancialDecimal::compare($price, '0') <= 0) {
                continue;
            }
            $take = FinancialDecimal::min($remaining, $available);
            $filled = FinancialDecimal::add($filled, $take);
            $quote = FinancialDecimal::add($quote, FinancialDecimal::mul($take, $price));
            $remaining = FinancialDecimal::sub($remaining, $take);
            $lastPrice = $price;
        }

        $complete = FinancialDecimal::compare($filled, $quantity) >= 0;

        return [
            'source' => $this->code(),
            'source_type' => $book['source_type'],
            'executable' => (bool) $book['executable'],
            'quantity' => $filled,
            'quote_quantity' => $quote,
            'average_price' => FinancialDecimal::compare($filled, '0') > 0 ? FinancialDecimal::div($quote, $filled) : '0',
            'worst_price' => $lastPrice,
            'complete' => $complete,
            'status' => $complete ? 'QUOTED' : 'INSUFFICIENT_DEPTH',
        ];
    }

    public function placeOrder(array $order): array
    {
        if ($this->state() !== 'LIVE') {
            throw new RuntimeException('External venue is not LIVE for authenticated execution.');
        }

        throw new RuntimeException('Authenticated Binance execution adapter is not configured.');
    }

    public function cancelOrder(string $symbol, string $clientOrderId): array
    {
        return ['venue' => $this->code(), 'symbol' => strtoupper($symbol), 'client_order_id' => $clientOrderId, 'status' => 'UNSUPPORTED_NOT_LIVE'];
    }

    public function getOrder(string $symbol, string $clientOrderId): array
    {
        return ['venue' => $this->code(), 'symbol' => strtoupper($symbol), 'client_order_id' => $clientOrderId, 'status' => 'UNSUPPORTED_NOT_LIVE'];
    }

    public function getOpenOrders(string $symbol): array
    {
        return [];
    }

    public function getTrades(string $symbol, string $clientOrderId): array
    {
        return [];
    }

    public function getBalances(): array
    {
        return [];
    }

    public function getTradingFees(string $symbol): array
    {
        return ['maker_bps' => '10', 'taker_bps' => '10', 'status' => 'REFERENCE_DEFAULT'];
    }
}
