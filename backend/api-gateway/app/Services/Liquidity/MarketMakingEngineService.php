<?php

declare(strict_types=1);

namespace App\Services\Liquidity;

use App\Models\Market;
use App\Models\MarketMakerAccount;
use App\Models\MarketMakerQuote;
use App\Services\FinancialDecimal;
use App\Services\PriceProtectionService;
use Illuminate\Support\Str;

class MarketMakingEngineService
{
    public function __construct(
        private readonly PriceProtectionService $prices,
        private readonly WithdrawalLiquidityReserveService $withdrawalReserve,
        private readonly TreasuryInventoryService $inventory,
    ) {
    }

    public function quote(Market $market, MarketMakerAccount $account, string $referencePrice, string $quantity): array
    {
        if (! (bool) config('liquidity.market_making.enabled', false)) {
            return ['status' => 'DISABLED', 'quotes' => []];
        }
        if ($account->status !== 'ACTIVE') {
            return ['status' => 'ACCOUNT_NOT_ACTIVE', 'quotes' => []];
        }

        $base = strtoupper((string) $market->base_currency);
        $quoteAsset = strtoupper((string) $market->quote_currency);
        $referencePrice = FinancialDecimal::normalize($referencePrice);
        $quantity = FinancialDecimal::normalize($quantity);
        $spreadBps = (string) data_get($account->limits, 'spread_bps', config('liquidity.market_making.default_spread_bps', '20'));
        $halfSpread = FinancialDecimal::div($spreadBps, '20000');
        $bid = FinancialDecimal::mul($referencePrice, FinancialDecimal::sub('1', $halfSpread));
        $ask = FinancialDecimal::mul($referencePrice, FinancialDecimal::add('1', $halfSpread));

        $this->prices->quality((string) $market->symbol, $referencePrice, now()->toISOString());
        $this->withdrawalReserve->assertProtected($base, $quantity);
        $this->withdrawalReserve->assertProtected($quoteAsset, FinancialDecimal::mul($quantity, $bid));

        $ttl = now()->addSeconds((int) config('liquidity.market_making.quote_ttl_seconds', 15));
        $quotes = [];
        foreach ([['buy', $bid, $quoteAsset, FinancialDecimal::mul($quantity, $bid)], ['sell', $ask, $base, $quantity]] as [$side, $price, $asset, $reserved]) {
            $quotes[] = MarketMakerQuote::query()->create([
                'quote_id' => (string) Str::uuid(),
                'market_maker_account_id' => $account->id,
                'market_symbol' => $market->symbol,
                'side' => $side,
                'price' => $price,
                'quantity' => $quantity,
                'reserved_inventory' => $reserved,
                'status' => 'ACTIVE',
                'expires_at' => $ttl,
                'metadata' => ['reserved_asset' => $asset, 'source' => 'phase8_market_making'],
            ]);
        }

        return ['status' => 'QUOTED', 'quotes' => $quotes];
    }

    public function cancelUnsafe(string $reason = 'phase8_safety'): int
    {
        return MarketMakerQuote::query()
            ->where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '<=', now()))
            ->update(['status' => 'CANCELLED', 'metadata' => ['cancel_reason' => $reason]]);
    }
}
