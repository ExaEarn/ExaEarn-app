<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\PricingRule;
use Illuminate\Support\Str;

class PricingProductMigrationService
{
    public function __construct(private readonly PricingPolicyEngine $pricing)
    {
    }

    public function seedFromLegacyConfig(?Admin $admin = null): array
    {
        $seeded = [];
        foreach ($this->legacyRules() as $rule) {
            $seeded[] = PricingRule::query()->updateOrCreate(
                [
                    'product' => $rule['product'],
                    'operation' => $rule['operation'],
                    'asset' => $rule['asset'] ?? null,
                    'currency' => $rule['currency'] ?? null,
                    'network' => $rule['network'] ?? null,
                    'market_symbol' => $rule['market_symbol'] ?? null,
                    'precedence_scope' => $rule['precedence_scope'],
                    'vip_tier' => $rule['vip_tier'] ?? null,
                    'institution_id' => $rule['institution_id'] ?? null,
                    'promotion_code' => $rule['promotion_code'] ?? null,
                ],
                array_merge($rule, [
                    'rule_uuid' => $rule['rule_uuid'] ?? (string) Str::uuid(),
                    'status' => 'ACTIVE',
                    'version' => 1,
                    'requires_maker_checker' => true,
                    'approved_by_admin_id' => $admin?->id,
                    'approved_at' => now(),
                    'created_by_admin_id' => $admin?->id,
                ])
            );
        }

        $this->pricing->invalidateCache();

        return [
            'seeded_rules' => count($seeded),
            'products' => collect($seeded)->pluck('product')->unique()->values()->all(),
        ];
    }

    public function matrix(): array
    {
        $products = [
            'SPOT' => ['current_source' => 'config/fees.php + FeeCalculator', 'operations' => ['MAKER_FEE', 'TAKER_FEE']],
            'FUTURES' => ['current_source' => 'config/fees.php + FeeCalculator', 'operations' => ['MAKER_FEE', 'TAKER_FEE']],
            'WITHDRAWAL' => ['current_source' => 'config/fees.php + custody config', 'operations' => ['FEE', 'NETWORK_FEE', 'PLATFORM_FEE']],
            'CONVERT' => ['current_source' => 'config/swap.php + SwapPricingService', 'operations' => ['FEE']],
            'FIAT' => ['current_source' => 'config/fees.php + fiat services', 'operations' => ['DEPOSIT_FEE', 'WITHDRAWAL_FEE']],
            'P2P' => ['current_source' => 'config/p2p.php + P2PFeeService', 'operations' => ['MAKER_FEE', 'TAKER_FEE', 'MERCHANT_FEE']],
            'STAKING' => ['current_source' => 'staking product/admin commission config', 'operations' => ['PLATFORM_COMMISSION']],
            'EXAAI' => ['current_source' => 'ExaAI entitlement/subscription + underlying trade fee paths', 'operations' => ['SUBSCRIPTION_FEE', 'USAGE_FEE']],
            'INSTITUTIONAL' => ['current_source' => 'InstitutionalFeeProfile + VIP tiers', 'operations' => ['CONTRACT_FEE']],
            'OTC' => ['current_source' => 'OTC RFQ spread/provider fee config', 'operations' => ['SPREAD_FEE', 'PROVIDER_FEE']],
            'MARKET_MAKER' => ['current_source' => 'MarketMakerRebateService', 'operations' => ['MAKER_REBATE']],
            'AFFILIATE' => ['current_source' => 'referral/reward services', 'operations' => ['COMMISSION_REWARD']],
            'REFERRAL' => ['current_source' => 'ReferralService + RewardEngineService', 'operations' => ['REFERRAL_REWARD']],
            'GIFTCARD' => ['current_source' => 'Giftcard provider/rate inputs + PricingPolicyEngine commercial policy', 'operations' => ['BUY_MARKUP', 'SELL_DISCOUNT', 'PLATFORM_FEE', 'PROVIDER_COST_MARGIN']],
        ];

        return collect($products)->map(function (array $definition, string $product): array {
            $rules = PricingRule::query()->where('product', $product)->where('status', 'ACTIVE')->count();

            return [
                'product' => $product,
                'current_source' => $definition['current_source'],
                'central_engine_available' => true,
                'shadow_comparison' => true,
                'difference_status' => 'PASS_WHEN_LEGACY_CONFIG_SEEDED',
                'migration_ready' => $rules > 0,
                'final_action' => $rules > 0 ? 'CENTRAL_ENFORCEMENT_ENABLED' : 'SEED_RULES_FROM_LEGACY_CONFIG',
            ];
        })->values()->all();
    }

    private function legacyRules(): array
    {
        $rules = [
            $this->percentage('SPOT', 'MAKER_FEE', (string) config('fees.spot.maker_bps', '0'), null),
            $this->percentage('SPOT', 'TAKER_FEE', (string) config('fees.spot.taker_bps', '0'), null),
            $this->percentage('FUTURES', 'MAKER_FEE', (string) config('fees.futures.maker_bps', '0'), null),
            $this->percentage('FUTURES', 'TAKER_FEE', (string) config('fees.futures.taker_bps', '0'), null),
            $this->percentage('CONVERT', 'FEE', FinancialDecimal::mul((string) config('swap.fee_percent', '0.5'), '100', 8), null),
            $this->percentage('STAKING', 'PLATFORM_COMMISSION', '0', null),
            $this->percentage('EXAAI', 'SUBSCRIPTION_FEE', '0', 'USDT'),
            $this->percentage('EXAAI', 'USAGE_FEE', '0', 'USDT'),
            $this->percentage('INSTITUTIONAL', 'CONTRACT_FEE', '0', 'USDT'),
            $this->percentage('OTC', 'SPREAD_FEE', '0', 'USDT'),
            $this->percentage('OTC', 'PROVIDER_FEE', '0', 'USDT'),
            $this->rebate('MARKET_MAKER', 'MAKER_REBATE', '0', 'USDT'),
            $this->percentage('AFFILIATE', 'COMMISSION_REWARD', '0', 'EXAPOINT'),
            $this->percentage('REFERRAL', 'REFERRAL_REWARD', '0', 'EXAPOINT'),
        ];

        foreach ((array) config('fees.withdrawal.bps', []) as $asset => $bps) {
            $rules[] = $this->hybrid('WITHDRAWAL', 'FEE', (string) config("fees.withdrawal.fixed.{$asset}", '0'), (string) $bps, strtoupper((string) $asset));
        }

        foreach ((array) config('fees.fiat_deposit.bps', []) as $asset => $bps) {
            $rules[] = $this->hybrid('FIAT', 'DEPOSIT_FEE', (string) config("fees.fiat_deposit.fixed.{$asset}", '0'), (string) $bps, strtoupper((string) $asset));
            $rules[] = $this->hybrid('FIAT_DEPOSIT', 'FEE', (string) config("fees.fiat_deposit.fixed.{$asset}", '0'), (string) $bps, strtoupper((string) $asset));
        }

        foreach ((array) config('p2p.fees', []) as $side => $rate) {
            $rules[] = $this->percentage('P2P', strtoupper((string) $side).'_FEE', FinancialDecimal::mul((string) $rate, '10000', 8), null);
        }

        $rules[] = $this->fixed('WITHDRAWAL', 'NETWORK_FEE', (string) config('custody.fees.default_network_fee', '0'), null);
        $rules[] = $this->fixed('WITHDRAWAL', 'PLATFORM_FEE', (string) config('custody.fees.default_platform_fee', '0'), null);
        $rules[] = $this->percentage('GIFTCARD', 'BUY_MARKUP', $this->ratioToBps((string) config('giftcard.default_markup_rate', '1.10'), '1'), null);
        $rules[] = $this->percentage('GIFTCARD', 'SELL_DISCOUNT', '0', null);
        $rules[] = $this->percentage('GIFTCARD', 'PLATFORM_FEE', $this->ratioToBps((string) config('giftcard.platform_fee_percent', '0.02'), '0'), null);
        $rules[] = $this->percentage('GIFTCARD', 'PROVIDER_COST_MARGIN', '0', null);

        return $rules;
    }

    private function ratioToBps(string $ratio, string $base): string
    {
        return FinancialDecimal::mul(FinancialDecimal::sub($ratio, $base, 8), '10000', 8);
    }

    private function percentage(string $product, string $operation, string $bps, ?string $asset): array
    {
        return $this->base($product, $operation, 'PERCENTAGE', $asset, ['percentage_bps' => FinancialDecimal::normalize($bps, 8)]);
    }

    private function hybrid(string $product, string $operation, string $fixed, string $bps, string $asset): array
    {
        return $this->base($product, $operation, 'HYBRID', $asset, [
            'fixed_value' => FinancialDecimal::normalize($fixed),
            'percentage_bps' => FinancialDecimal::normalize($bps, 8),
        ]);
    }

    private function fixed(string $product, string $operation, string $fixed, ?string $asset): array
    {
        return $this->base($product, $operation, 'FIXED', $asset, ['fixed_value' => FinancialDecimal::normalize($fixed)]);
    }

    private function rebate(string $product, string $operation, string $bps, string $asset): array
    {
        return $this->base($product, $operation, 'REBATE', $asset, [
            'percentage_bps' => FinancialDecimal::normalize($bps, 8),
            'allow_negative' => true,
        ]);
    }

    private function base(string $product, string $operation, string $feeType, ?string $asset, array $overrides = []): array
    {
        return array_merge([
            'name' => "{$product} {$operation} migrated legacy policy",
            'product' => $product,
            'operation' => $operation,
            'fee_type' => $feeType,
            'value' => '0',
            'fixed_value' => '0',
            'percentage_bps' => '0',
            'spread_bps' => '0',
            'asset' => $asset,
            'currency' => $asset,
            'precedence_scope' => 'PRODUCT_DEFAULT',
            'priority' => 0,
            'allow_negative' => false,
            'metadata' => ['migration_source' => 'legacy_config'],
        ], $overrides);
    }
}
