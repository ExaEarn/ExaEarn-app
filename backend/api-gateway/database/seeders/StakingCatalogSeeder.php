<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StakingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $assets = DB::table('staking_assets')
            ->whereNotIn('symbol', ['XRP', 'BTC', 'USDT', 'USDC', 'PI'])
            ->orderBy('symbol')
            ->get();

        foreach ($assets as $asset) {
            $slug = Str::of(strtolower($asset->symbol . '-' . $asset->network . '-native-staking'))
                ->replace(' ', '-')
                ->replace('/', '-')
                ->replace('--', '-')
                ->value();

            $name = sprintf('%s Native Staking', strtoupper((string) $asset->symbol));
            $minimum = (string) ($asset->minimum_stake ?? '0');
            $unbonding = $asset->unbonding_period_seconds ?? null;
            $durationDays = null;
            $supportsFlexible = (bool) ($asset->supports_flexible_staking ?? false);
            $productType = $supportsFlexible ? 'flexible_native_pos' : 'native_pos';

            DB::table('staking_products')->updateOrInsert(
                ['staking_asset_id' => $asset->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'product_type' => $productType,
                    'status' => 'active',
                    'network_environment' => $asset->testnet_enabled ? 'testnet' : 'testnet',
                    'duration_days' => $durationDays,
                    'minimum_amount' => $minimum,
                    'maximum_amount' => $asset->maximum_stake,
                    'displayed_apy' => $asset->displayed_apy,
                    'platform_commission_rate' => $asset->platform_commission_rate ?? '0',
                    'reward_schedule' => $asset->reward_distribution_frequency ?? 'verified_network_rewards',
                    'redemption_type' => 'network_unbonding',
                    'unbonding_period_seconds' => $unbonding,
                    'early_redemption_allowed' => false,
                    'early_redemption_penalty_rate' => '0',
                    'auto_compound_supported' => (bool) ($asset->auto_compound_supported ?? false),
                    'capacity' => null,
                    'total_subscribed' => '0',
                    'starts_at' => null,
                    'ends_at' => null,
                    'terms_version' => 'staking-v1',
                    'metadata' => json_encode([
                        'seeded' => true,
                        'seeded_at' => now()->toIso8601String(),
                        'network' => $asset->network,
                        'provider' => $asset->provider,
                        'configuration_required' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

