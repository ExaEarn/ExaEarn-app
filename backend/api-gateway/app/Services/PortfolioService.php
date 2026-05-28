<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CurrencyRate;
use App\Models\Giftcard;
use App\Models\GiftcardPortfolioRate;
use App\Models\Nft;
use App\Models\NftPrice;
use App\Models\PortfolioSnapshot;
use App\Models\PriceFeed;
use App\Models\UserAsset;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PortfolioService
{
    private const CACHE_TTL = 10; // seconds
    private const DECIMALS = 18;

    public function __construct(
        private readonly CurrencyConversionService $conversionService,
        private readonly PriceOracleService $oracleService,
    ) {
    }

    public function getUserPortfolioValue(int $userId, string $baseCurrency = 'USDT'): array
    {
        $baseCurrency = strtoupper($baseCurrency);
        $cacheKey = "portfolio_value:{$userId}:{$baseCurrency}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $baseCurrency) {
            return $this->computePortfolioValue($userId, $baseCurrency);
        });
    }

    public function invalidateCache(int $userId, string $baseCurrency = 'USDT'): void
    {
        Cache::forget("portfolio_value:{$userId}:{$baseCurrency}");
    }

    private function computePortfolioValue(int $userId, string $baseCurrency): array
    {
        $this->oracleService->refreshMarketData();

        $breakdown = [];
        $totalUsdt = '0';

        $userAssets = UserAsset::where('user_id', $userId)->get();

        if ($userAssets->isNotEmpty()) {
            foreach ($userAssets as $asset) {
                $value = $this->valueUserAsset($asset);

                if (bccomp($value, '0', self::DECIMALS) <= 0) {
                    continue;
                }

                $totalUsdt = bcadd($totalUsdt, $value, self::DECIMALS);
                $breakdown[] = [
                    'asset' => $asset->asset_symbol,
                    'type' => $asset->asset_type,
                    'value_usdt' => $value,
                ];
            }
        } else {
            $walletValues = $this->computeWalletPortfolio($userId);
            $giftcardValues = $this->computeGiftcardPortfolio($userId);
            $nftValues = $this->computeNftPortfolio($userId);

            foreach (array_merge($walletValues, $giftcardValues, $nftValues) as $item) {
                $totalUsdt = bcadd($totalUsdt, $item['value_usdt'], self::DECIMALS);
                $breakdown[] = $item;
            }
        }

        $convertedTotal = $baseCurrency === 'USDT'
            ? $totalUsdt
            : $this->conversionService->convert($totalUsdt, 'USDT', $baseCurrency);

        if ($baseCurrency !== 'USDT') {
            foreach ($breakdown as &$item) {
                $item['value'] = $this->conversionService->convert($item['value_usdt'], 'USDT', $baseCurrency);
            }
            unset($item);
        }

        $snapshot = PortfolioSnapshot::updateOrCreate(
            ['user_id' => $userId, 'base_currency' => $baseCurrency],
            [
                'total_value' => $convertedTotal,
                'breakdown' => $breakdown,
                'cached_at' => now(),
            ]
        );

        Log::info('Portfolio calculated', [
            'user_id' => $userId,
            'base_currency' => $baseCurrency,
            'total_value' => $convertedTotal,
            'snapshot_id' => $snapshot->id,
        ]);

        return [
            'total_value' => $convertedTotal,
            'currency' => $baseCurrency,
            'breakdown' => $breakdown,
        ];
    }

    private function valueUserAsset(UserAsset $asset): string
    {
        $balance = bcadd((string) $asset->balance, (string) $asset->locked_balance, self::DECIMALS);
        if (bccomp($balance, '0', self::DECIMALS) <= 0) {
            return '0';
        }

        return match ($asset->asset_type) {
            'crypto' => $this->valueCryptoAsset($asset->asset_symbol, $balance),
            'fiat' => $this->valueFiatAsset($asset->asset_symbol, $balance),
            'giftcard' => $this->valueGiftcardAsset($asset->asset_symbol, $balance),
            'nft' => $this->valueNftAsset($asset->asset_symbol),
            default => '0',
        };
    }

    private function computeWalletPortfolio(int $userId): array
    {
        $output = [];
        $wallets = Wallet::where('user_id', $userId)->get();

        foreach ($wallets as $wallet) {
            $symbol = strtoupper($wallet->currency);
            $balance = bcadd((string) $wallet->available_balance, (string) $wallet->locked_balance, self::DECIMALS);

            if (bccomp($balance, '0', self::DECIMALS) <= 0) {
                continue;
            }

            $type = $this->resolveWalletAssetType($symbol);
            $valueUsdt = $type === 'fiat'
                ? $this->valueFiatAsset($symbol, $balance)
                : $this->valueCryptoAsset($symbol, $balance);

            if (bccomp($valueUsdt, '0', self::DECIMALS) <= 0) {
                continue;
            }

            $output[] = [
                'asset' => $symbol,
                'type' => $type,
                'balance' => $balance,
                'value_usdt' => $valueUsdt,
            ];
        }

        return $output;
    }

    private function computeGiftcardPortfolio(int $userId): array
    {
        $output = [];

        foreach (Giftcard::where('owner_user_id', $userId)->where('status', 'active')->get() as $giftcard) {
            $symbol = strtoupper($giftcard->card_type);
            $valueUsdt = $this->valueGiftcardAsset($symbol, (string) $giftcard->amount);

            if (bccomp($valueUsdt, '0', self::DECIMALS) <= 0) {
                continue;
            }

            $output[] = [
                'asset' => $symbol,
                'type' => 'giftcard',
                'balance' => (string) $giftcard->amount,
                'value_usdt' => $valueUsdt,
            ];
        }

        return $output;
    }

    private function computeNftPortfolio(int $userId): array
    {
        $output = [];

        foreach (Nft::where('user_id', $userId)->get() as $nft) {
            $valueUsdt = $this->valueNftAsset($nft->nft_uuid);

            if (bccomp($valueUsdt, '0', self::DECIMALS) <= 0) {
                continue;
            }

            $output[] = [
                'asset' => $nft->nft_uuid,
                'type' => 'nft',
                'collection' => $nft->collection?->name ?? null,
                'value_usdt' => $valueUsdt,
            ];
        }

        return $output;
    }

    private function valueCryptoAsset(string $symbol, string $amount): string
    {
        $price = PriceFeed::where('asset_symbol', $symbol)->value('price_in_usdt');

        if ($price === null) {
            throw new RuntimeException("Missing crypto price for {$symbol}");
        }

        return bcmul($amount, (string) $price, self::DECIMALS);
    }

    private function valueFiatAsset(string $symbol, string $amount): string
    {
        $rate = CurrencyRate::where('currency', $symbol)->value('rate_to_usdt');

        if ($rate === null) {
            throw new RuntimeException("Missing currency rate for {$symbol}");
        }

        return bcmul($amount, (string) $rate, self::DECIMALS);
    }

    private function valueGiftcardAsset(string $cardType, string $amount): string
    {
        $rate = GiftcardPortfolioRate::where('card_type', strtoupper($cardType))->value('rate_to_usdt');

        if ($rate === null) {
            throw new RuntimeException("Missing giftcard rate for {$cardType}");
        }

        return bcmul($amount, (string) $rate, self::DECIMALS);
    }

    private function valueNftAsset(string $identifier): string
    {
        $price = NftPrice::where('nft_uuid', $identifier)->orWhere('nft_id', function ($query) use ($identifier) {
            $query->select('id')->from('nfts')->where('nft_uuid', $identifier)->limit(1);
        })->first();

        if (! $price) {
            return '0';
        }

        $floor = (string) $price->floor_price_usdt;
        $lastSale = (string) $price->last_sale_price_usdt;

        return bccomp($lastSale, $floor, self::DECIMALS) >= 0 ? $lastSale : $floor;
    }

    private function resolveWalletAssetType(string $symbol): string
    {
        $config = config("wallet.assets.{$symbol}", []);

        if (($config['type'] ?? null) === 'fiat') {
            return 'fiat';
        }

        return 'crypto';
    }
}
