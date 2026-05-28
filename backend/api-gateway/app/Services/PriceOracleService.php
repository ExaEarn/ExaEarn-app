<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CurrencyRate;
use App\Models\GiftcardRate;
use App\Models\Nft;
use App\Models\NftPrice;
use App\Models\PriceFeed;
use App\Services\RealtimeStreamService;
use Illuminate\Support\Facades\Log;

class PriceOracleService
{
    public function __construct(
        private readonly RealtimeStreamService $streamService,
    ) {
    }

    public function refreshMarketData(): array
    {
        $cryptoPrices = $this->getCryptoPrices();
        foreach ($cryptoPrices as $symbol => $price) {
            PriceFeed::updateOrCreate(
                ['asset_symbol' => strtoupper($symbol)],
                [
                    'price_in_usdt' => $price,
                    'source' => 'internal',
                    'last_updated' => now(),
                ]
            );
        }

        $currencyRates = $this->getCurrencyRates();
        foreach ($currencyRates as $currency => $rate) {
            CurrencyRate::updateOrCreate(
                ['currency' => strtoupper($currency)],
                [
                    'rate_to_usdt' => $rate,
                    'source' => 'internal',
                    'last_updated' => now(),
                ]
            );
        }

        $giftcardRates = $this->getGiftcardRates();
        foreach ($giftcardRates as $cardType => $rate) {
            GiftcardRate::updateOrCreate(
                ['card_type' => strtoupper($cardType)],
                [
                    'rate_to_usdt' => $rate,
                    'currency' => 'USDT',
                    'source' => 'internal',
                    'last_updated' => now(),
                ]
            );
        }

        $nfts = Nft::with('collection')->get();
        foreach ($nfts as $nft) {
            $this->refreshNftPrice($nft);
        }

        Log::info('Market oracle refreshed', [
            'crypto_prices' => array_keys($cryptoPrices),
            'currencies' => array_keys($currencyRates),
            'giftcards' => array_keys($giftcardRates),
            'nft_count' => $nfts->count(),
        ]);

        $this->streamService->publishMarketPriceUpdate([
            'crypto' => $cryptoPrices,
            'currencies' => $currencyRates,
            'giftcards' => $giftcardRates,
            'nft_count' => $nfts->count(),
        ]);

        return [
            'crypto' => $cryptoPrices,
            'currencies' => $currencyRates,
            'giftcards' => $giftcardRates,
        ];
    }

    private function refreshNftPrice(Nft $nft): void
    {
        $floor = $this->determineNftFloorPriceUsdt($nft);
        $lastSale = $this->determineNftLastSalePriceUsdt($nft);

        NftPrice::updateOrCreate(
            ['nft_id' => $nft->id],
            [
                'nft_uuid' => $nft->nft_uuid,
                'collection_name' => $nft->collection?->name ?? null,
                'floor_price_usdt' => $floor,
                'last_sale_price_usdt' => $lastSale,
                'source' => 'internal',
                'last_updated' => now(),
            ]
        );
    }

    private function determineNftFloorPriceUsdt(Nft $nft): string
    {
        if (bccomp((string) $nft->current_value_exa, '0', 18) > 0) {
            return bcmul((string) $nft->current_value_exa, '0.8', 18);
        }

        return '100.000000000000000000';
    }

    private function determineNftLastSalePriceUsdt(Nft $nft): string
    {
        if (bccomp((string) $nft->current_value_exa, '0', 18) > 0) {
            return bcmul((string) $nft->current_value_exa, '0.9', 18);
        }

        return '120.000000000000000000';
    }

    private function getCryptoPrices(): array
    {
        return [
            'BTC' => '30000.000000000000000000',
            'ETH' => '1900.000000000000000000',
            'USDT' => '1.000000000000000000',
        ];
    }

    private function getCurrencyRates(): array
    {
        return [
            'NGN' => '0.001350000000000000',
            'USD' => '1.000000000000000000',
            'ZAR' => '0.055000000000000000',
        ];
    }

    private function getGiftcardRates(): array
    {
        return [
            'AMAZON' => '0.950000000000000000',
            'STEAM' => '0.900000000000000000',
        ];
    }
}
