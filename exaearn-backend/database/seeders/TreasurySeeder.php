<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TreasuryWallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class TreasurySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create hot wallets for different chains
        $hotWallets = [
            [
                'type' => 'hot',
                'chain' => 'ethereum',
                'address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', // Example address
                'label' => 'Ethereum Hot Wallet',
                'status' => 'active',
                'metadata' => [
                    'encrypted_private_key' => Crypt::encryptString('example_private_key_eth'), // Replace with real encrypted key
                    'balances' => [
                        'ETH' => '0',
                        'USDT' => '0',
                        'USDC' => '0',
                    ],
                ],
            ],
            [
                'type' => 'hot',
                'chain' => 'bitcoin',
                'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', // Example address
                'label' => 'Bitcoin Hot Wallet',
                'status' => 'active',
                'metadata' => [
                    'encrypted_private_key' => Crypt::encryptString('example_private_key_btc'), // Replace with real encrypted key
                    'balances' => [
                        'BTC' => '0',
                    ],
                ],
            ],
            [
                'type' => 'hot',
                'chain' => 'polygon',
                'address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44f', // Example address
                'label' => 'Polygon Hot Wallet',
                'status' => 'active',
                'metadata' => [
                    'encrypted_private_key' => Crypt::encryptString('example_private_key_matic'), // Replace with real encrypted key
                    'balances' => [
                        'MATIC' => '0',
                        'USDT' => '0',
                        'USDC' => '0',
                    ],
                ],
            ],
        ];

        // Create cold wallets for different chains
        $coldWallets = [
            [
                'type' => 'cold',
                'chain' => 'ethereum',
                'address' => '0x8ba1f109551bD432803012645ac136ddd64DBA72', // Example address
                'label' => 'Ethereum Cold Wallet',
                'status' => 'active',
                'metadata' => [
                    'balances' => [
                        'ETH' => '0',
                        'USDT' => '0',
                        'USDC' => '0',
                    ],
                ],
            ],
            [
                'type' => 'cold',
                'chain' => 'bitcoin',
                'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', // Example address
                'label' => 'Bitcoin Cold Wallet',
                'status' => 'active',
                'metadata' => [
                    'balances' => [
                        'BTC' => '0',
                    ],
                ],
            ],
            [
                'type' => 'cold',
                'chain' => 'polygon',
                'address' => '0x8ba1f109551bD432803012645ac136ddd64DBA73', // Example address
                'label' => 'Polygon Cold Wallet',
                'status' => 'active',
                'metadata' => [
                    'balances' => [
                        'MATIC' => '0',
                        'USDT' => '0',
                        'USDC' => '0',
                    ],
                ],
            ],
        ];

        // Insert wallets
        foreach (array_merge($hotWallets, $coldWallets) as $wallet) {
            TreasuryWallet::create($wallet);
        }

        $this->command->info('Treasury wallets seeded successfully.');
        $this->command->warn('⚠️  IMPORTANT: Replace example private keys and addresses with real ones!');
        $this->command->warn('⚠️  Set TREASURY_KEY_SECRET environment variable for key encryption.');
    }
}