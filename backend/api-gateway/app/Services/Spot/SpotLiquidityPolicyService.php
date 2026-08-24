<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;

class SpotLiquidityPolicyService
{
    public const INTERNAL_ONLY = 'INTERNAL_ONLY';
    public const HYBRID = 'HYBRID';
    public const EXTERNAL_ASSISTED = 'EXTERNAL_ASSISTED';
    public const DISABLED = 'DISABLED';

    public const PRICE_REFERENCE_ASSISTED = 'REFERENCE_ASSISTED';
    public const PRICE_HYBRID = 'HYBRID';
    public const PRICE_EXAEARN_PRIMARY = 'EXAEARN_PRIMARY';

    public function policyFor(Market $market): array
    {
        $symbol = strtoupper((string) $market->symbol);
        $mode = strtoupper((string) ($market->liquidity_mode ?: config('trading.liquidity.default_mode', self::INTERNAL_ONLY)));
        $priceAuthority = strtoupper((string) ($market->price_authority_mode ?: config('trading.liquidity.default_price_authority', self::PRICE_REFERENCE_ASSISTED)));
        $globalExternal = (bool) config('trading.liquidity.external_routing_enabled', false);
        $marketExternal = (bool) $market->external_routing_enabled;
        $externalAllowedByMode = in_array($mode, [self::HYBRID, self::EXTERNAL_ASSISTED], true);

        return [
            'symbol' => $symbol,
            'liquidity_mode' => in_array($mode, [self::INTERNAL_ONLY, self::HYBRID, self::EXTERNAL_ASSISTED, self::DISABLED], true) ? $mode : self::INTERNAL_ONLY,
            'price_authority_mode' => in_array($priceAuthority, [self::PRICE_REFERENCE_ASSISTED, self::PRICE_HYBRID, self::PRICE_EXAEARN_PRIMARY], true) ? $priceAuthority : self::PRICE_REFERENCE_ASSISTED,
            'external_routing_enabled' => $globalExternal && $marketExternal && $externalAllowedByMode,
            'shadow_only' => (bool) data_get($market->external_routing_policy, 'shadow_only', config('trading.liquidity.shadow_only', true)),
            'max_external_percent_per_order' => (string) data_get($market->external_routing_policy, 'max_external_percent_per_order', config('trading.liquidity.max_external_percent_per_order', '0')),
            'max_external_notional' => (string) data_get($market->external_routing_policy, 'max_external_notional', config('trading.liquidity.max_external_notional', '0')),
            'max_slippage_bps' => (string) data_get($market->external_routing_policy, 'max_slippage_bps', config('trading.liquidity.max_slippage_bps', '50')),
        ];
    }

    public function canUseExternalFallback(Market $market): bool
    {
        $policy = $this->policyFor($market);

        return $policy['external_routing_enabled'] === true && $policy['shadow_only'] === false;
    }
}
