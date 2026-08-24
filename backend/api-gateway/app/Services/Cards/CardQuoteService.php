<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\CardFundingQuote;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use Illuminate\Support\Str;
use RuntimeException;

class CardQuoteService
{
    public function __construct(
        private readonly CardProductService $products,
        private readonly PricingPolicyEngine $pricing,
    ) {
    }

    public function createFundingQuote(User $user, Card $card, string $sourceAsset, string $cardAmount): CardFundingQuote
    {
        $product = $this->products->find((string) $card->card_product);
        $sourceAsset = strtoupper($sourceAsset);
        $cardCurrency = strtoupper((string) $card->currency);
        $amount = FinancialDecimal::normalize($cardAmount);

        if (! in_array($sourceAsset, array_map('strtoupper', (array) ($product['allowed_funding_assets'] ?? [])), true)) {
            throw new RuntimeException('Funding asset is not supported for this card product.');
        }
        if (FinancialDecimal::compare($amount, (string) ($product['minimum_funding'] ?? '0')) < 0) {
            throw new RuntimeException('Funding amount is below the product minimum.');
        }
        if (FinancialDecimal::compare($amount, (string) ($product['maximum_funding'] ?? '0')) > 0) {
            throw new RuntimeException('Funding amount exceeds the product maximum.');
        }

        $fxRate = $this->fxRate($sourceAsset, $cardCurrency);
        $sourceAmount = FinancialDecimal::div($amount, $fxRate);
        $pricing = $this->pricingPreview($user, $sourceAmount, $sourceAsset);
        $cardFee = (string) ($pricing['fee_amount'] ?? '0');
        $providerFee = '0.000000000000000000';
        $totalDebit = FinancialDecimal::add($sourceAmount, FinancialDecimal::add($cardFee, $providerFee));

        return CardFundingQuote::query()->create([
            'quote_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'card_id' => $card->id,
            'source_asset' => $sourceAsset,
            'card_currency' => $cardCurrency,
            'source_amount' => $sourceAmount,
            'card_amount' => $amount,
            'fx_rate' => $fxRate,
            'conversion_fee' => '0',
            'card_fee' => $cardFee,
            'provider_fee' => $providerFee,
            'provider_cost' => '0',
            'total_debit' => $totalDebit,
            'pricing_snapshot' => $pricing,
            'status' => 'QUOTED',
            'expires_at' => now()->addSeconds((int) config('exacard.quote_ttl_seconds', 60)),
            'metadata' => ['product' => $product['product_code']],
        ]);
    }

    private function pricingPreview(User $user, string $amount, string $asset): array
    {
        try {
            return $this->pricing->preview([
                'user_id' => $user->id,
                'product' => 'EXACARD',
                'operation' => 'CARD_FUNDING',
                'amount' => $amount,
                'asset' => $asset,
                'currency' => $asset,
            ]);
        } catch (\Throwable $exception) {
            if ($this->pricing->isEnforced('EXACARD')) {
                throw $exception;
            }

            return [
                'source' => 'FALLBACK_ZERO_FEE_UNENFORCED',
                'product' => 'EXACARD',
                'operation' => 'CARD_FUNDING',
                'asset' => $asset,
                'currency' => $asset,
                'gross_amount' => $amount,
                'fee_amount' => '0.000000000000000000',
                'net_amount' => $amount,
            ];
        }
    }

    private function fxRate(string $sourceAsset, string $cardCurrency): string
    {
        if ($sourceAsset === $cardCurrency) {
            return '1.000000000000000000';
        }

        $stableDollar = ['USD', 'USDT', 'USDC'];
        if (in_array($sourceAsset, $stableDollar, true) && in_array($cardCurrency, $stableDollar, true)) {
            return '1.000000000000000000';
        }

        throw new RuntimeException('Card funding conversion route is unavailable for this currency pair.');
    }
}
