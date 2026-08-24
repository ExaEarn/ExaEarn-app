<?php

declare(strict_types=1);

namespace App\Services\Custody;

use Illuminate\Support\Facades\DB;

class CustodyRegistryService
{
    public function syncFromConfig(): void
    {
        DB::transaction(function (): void {
            foreach ((array) config('custody.networks', []) as $network => $settings) {
                DB::table('blockchain_networks')->updateOrInsert(
                    ['network' => $network],
                    [
                        'family' => (string) $settings['family'],
                        'chain_id' => $settings['chain_id'] ?? null,
                        'native_asset' => strtoupper((string) $settings['native_asset']),
                        'state' => (string) ($settings['state'] ?? 'DEGRADED'),
                        'deposit_enabled' => (bool) ($settings['deposit_enabled'] ?? false),
                        'withdrawal_enabled' => (bool) ($settings['withdrawal_enabled'] ?? false),
                        'required_confirmations' => (int) ($settings['required_confirmations'] ?? 1),
                        'finality_confirmations' => (int) ($settings['finality_confirmations'] ?? ($settings['required_confirmations'] ?? 1)),
                        'memo_required' => (bool) ($settings['memo_required'] ?? false),
                        'policy' => json_encode($settings, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ((array) config('custody.assets', []) as $asset => $settings) {
                foreach ((array) ($settings['networks'] ?? []) as $network) {
                    $networkId = DB::table('blockchain_networks')->where('network', $network)->value('id');
                    if (!$networkId) {
                        continue;
                    }

                    DB::table('blockchain_assets')->updateOrInsert(
                        ['asset' => strtoupper($asset), 'network' => $network, 'contract_address' => $settings['contract_address'] ?? null],
                        [
                            'blockchain_network_id' => $networkId,
                            'asset_type' => (string) ($settings['type'] ?? 'native'),
                            'decimals' => (int) ($settings['decimals'] ?? 18),
                            'deposit_enabled' => true,
                            'withdrawal_enabled' => true,
                            'minimum_deposit' => (string) config('custody.limits.default_min_deposit', '0.00000001'),
                            'minimum_withdrawal' => (string) config('custody.limits.default_min_withdrawal', '0.00000001'),
                            'maximum_withdrawal' => (string) config('custody.limits.default_max_withdrawal', '1000000'),
                            'required_confirmations' => (int) config("custody.networks.{$network}.required_confirmations", 1),
                            'sweep_threshold' => (string) ($settings['sweep_threshold'] ?? '0'),
                            'rebalance_threshold' => (string) ($settings['rebalance_threshold'] ?? '0'),
                            'fee_policy' => json_encode($settings['fee_policy'] ?? [], JSON_THROW_ON_ERROR),
                            'metadata' => json_encode($settings, JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    public function network(string $network): array
    {
        $this->syncFromConfig();
        $row = DB::table('blockchain_networks')->where('network', strtolower($network))->first();

        if (!$row) {
            throw new \RuntimeException('Unsupported blockchain network.');
        }

        return (array) $row;
    }

    public function asset(string $asset, string $network): array
    {
        $this->syncFromConfig();
        $row = DB::table('blockchain_assets')
            ->where('asset', strtoupper($asset))
            ->where('network', strtolower($network))
            ->first();

        if (!$row) {
            throw new \RuntimeException('Unsupported asset/network pair.');
        }

        $data = (array) $row;
        $settings = (array) config("custody.assets.{$asset}", []);
        $data['minimum_deposit'] = (string) ($settings['minimum_deposit'] ?? config('custody.limits.default_min_deposit', '0.00000001'));
        $data['minimum_withdrawal'] = (string) ($settings['minimum_withdrawal'] ?? config('custody.limits.default_min_withdrawal', '0.00000001'));
        $data['maximum_withdrawal'] = (string) ($settings['maximum_withdrawal'] ?? config('custody.limits.default_max_withdrawal', '1000000'));

        return $data;
    }
}
