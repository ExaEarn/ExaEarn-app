<?php

declare(strict_types=1);

namespace App\Services\Custody;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CustodyAddressService
{
    public function __construct(private readonly CustodyRegistryService $registry)
    {
    }

    public function getOrCreateDepositAddress(User $user, string $asset, string $network): array
    {
        $asset = strtoupper($asset);
        $network = strtolower($network);
        $networkConfig = $this->registry->network($network);
        $assetConfig = $this->registry->asset($asset, $network);

        if (!(bool) $networkConfig['deposit_enabled'] || !(bool) $assetConfig['deposit_enabled']) {
            throw new RuntimeException('Deposits are not enabled for this asset/network.');
        }

        return DB::transaction(function () use ($asset, $assetConfig, $network, $networkConfig, $user): array {
            $existing = DB::table('custody_address_assignments')
                ->join('custody_addresses', 'custody_addresses.id', '=', 'custody_address_assignments.custody_address_id')
                ->where('custody_address_assignments.user_id', $user->id)
                ->where('custody_address_assignments.asset', $asset)
                ->where('custody_address_assignments.network', $network)
                ->where('custody_address_assignments.status', 'ACTIVE')
                ->select('custody_addresses.*')
                ->first();

            if ($existing) {
                return $this->presentAddress((array) $existing, $asset, $network, $assetConfig, $networkConfig);
            }

            $wallet = DB::table('custody_wallets')
                ->where('network', $network)
                ->whereIn('classification', ['USER_DEPOSIT', 'HOT'])
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->first();

            if (!$wallet && !app()->environment(['local', 'testing'])) {
                throw new RuntimeException('Production custody wallet is not configured for this network.');
            }

            $memoTag = (bool) $networkConfig['memo_required'] ? (string) (100000000 + (int) $user->id) : null;
            $address = $wallet?->address ?: $this->developmentAddress($network, (int) $user->id);

            $addressId = (string) Str::uuid();
            $custodyAddressPk = DB::table('custody_addresses')->insertGetId([
                'address_id' => $addressId,
                'custody_wallet_id' => $wallet?->id,
                'network' => $network,
                'address' => $address,
                'memo_tag' => $memoTag,
                'address_type' => 'USER_DEPOSIT',
                'derivation_reference' => $wallet ? 'configured-wallet' : 'development-derived',
                'derivation_index' => $user->id,
                'status' => 'ACTIVE',
                'metadata' => json_encode(['production_ready' => (bool) $wallet], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('custody_address_assignments')->insert([
                'custody_address_id' => $custodyAddressPk,
                'user_id' => $user->id,
                'asset' => $asset,
                'network' => $network,
                'status' => 'ACTIVE',
                'metadata' => json_encode(['source' => 'custody_address_service'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('custody_addresses')->where('id', $custodyAddressPk)->first();

            return $this->presentAddress((array) $row, $asset, $network, $assetConfig, $networkConfig);
        });
    }

    private function presentAddress(array $row, string $asset, string $network, array $assetConfig, array $networkConfig): array
    {
        return [
            'asset' => $asset,
            'network' => $network,
            'address' => $row['address'],
            'memo_tag' => $row['memo_tag'] ?? null,
            'minimum_deposit' => (string) $assetConfig['minimum_deposit'],
            'required_confirmations' => (int) $assetConfig['required_confirmations'],
            'deposit_status' => (bool) $assetConfig['deposit_enabled'] ? 'ENABLED' : 'DISABLED',
            'qr_payload' => (string) $row['address'] . (($row['memo_tag'] ?? null) ? '?dt=' . $row['memo_tag'] : ''),
            'warning' => (bool) $networkConfig['memo_required']
                ? 'This network requires the destination tag/memo. Missing tags cannot be automatically credited.'
                : 'Send only supported assets on the selected network.',
        ];
    }

    private function developmentAddress(string $network, int $userId): string
    {
        $hash = substr(hash('sha256', $network.'|'.$userId.'|exaearn'), 0, 40);

        return match ((string) config("custody.networks.{$network}.family")) {
            'evm' => '0x'.$hash,
            'xrpl' => 'r'.substr(hash('sha256', $network.'|'.$userId), 0, 33),
            'tron' => 'T'.substr(hash('sha256', $network.'|'.$userId), 0, 33),
            'solana' => substr(hash('sha256', $network.'|'.$userId), 0, 44),
            default => substr(hash('sha256', $network.'|'.$userId), 0, 34),
        };
    }
}
