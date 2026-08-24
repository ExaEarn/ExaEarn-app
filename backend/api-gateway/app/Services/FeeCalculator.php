<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstitutionalAccount;
use InvalidArgumentException;

class FeeCalculator
{
    private const SCALE = 18;

    public function __construct(private readonly ?PricingPolicyEngine $pricing = null)
    {
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string}
     */
    public function withdrawal(string $amount, string $asset): array
    {
        return $this->withFixedAndBps('withdrawal', $amount, $asset);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string, liquidity_role:string}
     */
    public function spot(string $notional, string $asset, string $liquidityRole = 'taker'): array
    {
        return $this->marketFee('spot', $notional, $asset, $liquidityRole);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string, liquidity_role:string}
     */
    public function futures(string $notional, string $asset = 'USDT', string $liquidityRole = 'taker'): array
    {
        return $this->marketFee('futures', $notional, $asset, $liquidityRole);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string, liquidity_role:string, fee_policy_snapshot:array}
     */
    public function institutionalMarket(InstitutionalAccount $institution, string $product, string $market, string $notional, string $asset, string $liquidityRole = 'taker'): array
    {
        $base = $this->marketFee(strtolower($product), $notional, $asset, $liquidityRole);
        $role = strtolower($liquidityRole) === 'maker' ? 'maker' : 'taker';
        $rules = $institution->feeProfile?->rules ?? [];
        $vipRules = (array) config('fees.vip', []);
        $profileRate = $rules[strtoupper($product)][$market][$role.'_bps'] ?? $rules[strtoupper($product)]['*'][$role.'_bps'] ?? null;
        $vipRate = $vipRules[$institution->vip_tier][strtolower($product)][$role.'_bps'] ?? null;
        $chosen = $profileRate ?? $vipRate ?? $base['rate_bps'];
        $fee = $this->bps($notional, (string) $chosen);

        $legacy = array_merge($base, [
            'fee_amount' => $this->fmt($fee),
            'net_amount' => $this->fmt($this->sub($notional, $fee)),
            'rate_bps' => (string) $chosen,
            'fee_policy_snapshot' => [
                'institution_id' => $institution->id,
                'vip_tier' => $institution->vip_tier,
                'fee_profile_id' => $institution->fee_profile_id,
                'product' => strtoupper($product),
                'market' => $market,
                'liquidity_role' => $role,
                'precedence' => $profileRate !== null ? 'institutional_fee_profile' : ($vipRate !== null ? 'vip_tier' : 'standard_fee_config'),
            ],
        ]);

        return $this->engineBackedQuote($legacy, [
            'product' => strtoupper($product),
            'operation' => strtoupper($role).'_FEE',
            'amount' => $notional,
            'asset' => $asset,
            'currency' => $asset,
            'market_symbol' => $market,
            'institution_id' => $institution->id,
            'vip_tier' => $institution->vip_tier,
        ], ['liquidity_role' => $role]);
    }

    /**
     * @return array{source:string, asset:string, gross_amount:string, fee_amount:string, net_amount:string, rate_bps:string, fixed_fee:string}
     */
    public function fiatDeposit(string $amount, string $asset = 'NGN'): array
    {
        return $this->withFixedAndBps('fiat_deposit', $amount, $asset);
    }

    private function withFixedAndBps(string $source, string $amount, string $asset): array
    {
        $asset = strtoupper($asset);
        $this->assertPositive($amount);

        $bps = (string) config("fees.{$source}.bps.{$asset}", '0');
        $fixed = (string) config("fees.{$source}.fixed.{$asset}", '0');
        $fee = $this->add($this->bps($amount, $bps), $fixed);

        if ($this->compare($fee, $amount) >= 0) {
            throw new InvalidArgumentException('Fee must be lower than gross amount.');
        }

        $legacy = [
            'source' => $source,
            'asset' => $asset,
            'gross_amount' => $this->fmt($amount),
            'fee_amount' => $this->fmt($fee),
            'net_amount' => $this->fmt($this->sub($amount, $fee)),
            'rate_bps' => $bps,
            'fixed_fee' => $this->fmt($fixed),
        ];

        return $this->engineBackedQuote($legacy, [
            'product' => strtoupper($source),
            'operation' => 'FEE',
            'amount' => $amount,
            'asset' => $asset,
            'currency' => $asset,
        ]);
    }

    private function marketFee(string $source, string $notional, string $asset, string $liquidityRole): array
    {
        $asset = strtoupper($asset);
        $liquidityRole = strtolower($liquidityRole) === 'maker' ? 'maker' : 'taker';
        $this->assertPositive($notional);

        $bps = (string) config("fees.{$source}.{$liquidityRole}_bps", '0');
        $fee = $this->bps($notional, $bps);

        $legacy = [
            'source' => $source,
            'asset' => $asset,
            'gross_amount' => $this->fmt($notional),
            'fee_amount' => $this->fmt($fee),
            'net_amount' => $this->fmt($this->sub($notional, $fee)),
            'rate_bps' => $bps,
            'fixed_fee' => $this->fmt('0'),
            'liquidity_role' => $liquidityRole,
        ];

        return $this->engineBackedQuote($legacy, [
            'product' => strtoupper($source),
            'operation' => strtoupper($liquidityRole).'_FEE',
            'amount' => $notional,
            'asset' => $asset,
            'currency' => $asset,
        ], ['liquidity_role' => $liquidityRole]);
    }

    private function engineBackedQuote(array $legacy, array $context, array $extra = []): array
    {
        if (!config('pricing.enabled', true)) {
            return $legacy;
        }

        try {
            $pricing = $this->pricing ?? app(PricingPolicyEngine::class);
            $engine = $pricing->preview($context);
            $pricing->recordShadowComparison($context['product'], $context['operation'], $legacy['fee_amount'], $engine['fee_amount'], [
                'legacy' => $legacy,
                'engine_rule_uuid' => $engine['rule_uuid'],
            ]);

            if ((bool) config('pricing.shadow_mode', true)) {
                return array_merge($legacy, [
                    'pricing_engine_shadow' => [
                        'fee_amount' => $engine['fee_amount'],
                        'rule_uuid' => $engine['rule_uuid'],
                        'difference_amount' => FinancialDecimal::sub($engine['fee_amount'], $legacy['fee_amount']),
                    ],
                ]);
            }

            return array_merge($legacy, $extra, [
                'source' => strtolower($engine['product']),
                'fee_amount' => $engine['fee_amount'],
                'net_amount' => $engine['net_amount'],
                'rate_bps' => $engine['rate_bps'],
                'fixed_fee' => $engine['fixed_fee'],
                'fee_policy_snapshot' => $engine['fee_policy_snapshot'],
                'pricing_engine' => true,
            ]);
        } catch (\Throwable $exception) {
            if ($this->pricingEnforced($context['product'])) {
                throw $exception;
            }

            return $legacy;
        }
    }

    private function pricingEnforced(string $product): bool
    {
        return in_array(strtoupper($product), array_map('strtoupper', (array) config('pricing.enforced_products', [])), true);
    }

    private function bps(string $amount, string $bps): string
    {
        return $this->div($this->mul($amount, $bps), '10000');
    }

    private function assertPositive(string $amount): void
    {
        if ($this->compare($amount, '0') <= 0) {
            throw new InvalidArgumentException('Fee basis amount must be greater than zero.');
        }
    }

    private function fmt(string $value): string
    {
        return FinancialDecimal::normalize($value, self::SCALE);
    }

    private function add(string $a, string $b): string
    {
        return FinancialDecimal::add($a, $b, self::SCALE);
    }

    private function sub(string $a, string $b): string
    {
        return FinancialDecimal::sub($a, $b, self::SCALE);
    }

    private function mul(string $a, string $b): string
    {
        return FinancialDecimal::mul($a, $b, self::SCALE);
    }

    private function div(string $a, string $b): string
    {
        return FinancialDecimal::div($a, $b, self::SCALE);
    }

    private function compare(string $a, string $b): int
    {
        return FinancialDecimal::compare($a, $b, self::SCALE);
    }
}
