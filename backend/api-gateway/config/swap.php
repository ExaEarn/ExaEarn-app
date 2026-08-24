<?php

declare(strict_types=1);

return [
    'quote_ttl_seconds' => (int) env('SWAP_QUOTE_TTL_SECONDS', 20),
    'execution_retry_count' => (int) env('SWAP_EXECUTION_RETRY_COUNT', 3),
    'fee_percent' => (string) env('SWAP_FEE_PERCENT', '0.5'),
    'fx_spread_percent' => (string) env('SWAP_FX_SPREAD_PERCENT', '1.2'),
    'crypto_spread_percent' => (string) env('SWAP_CRYPTO_SPREAD_PERCENT', '0.4'),
    'supported_fiat' => ['NGN', 'USD', 'ZAR'],
    'supported_crypto' => ['BTC', 'ETH', 'USDT', 'BNB', 'TRX', 'SOL', 'XRP'],
    'treasury' => [
        'policy_version' => env('SWAP_TREASURY_POLICY_VERSION', 'convert-treasury-v1'),
        'enforced_assets' => array_filter(array_map(
            'trim',
            explode(',', env('SWAP_TREASURY_ENFORCED_ASSETS', 'BTC,ETH,BNB,TRX,SOL,XRP,NGN,USD,ZAR'))
        )),
        'asset_policies' => [
            'NGN' => [
                'approved_receivable' => env('SWAP_NGN_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_NGN_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_NGN_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_NGN_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_NGN_REBALANCE_THRESHOLD', '0'),
            ],
            'USD' => [
                'approved_receivable' => env('SWAP_USD_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_USD_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_USD_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_USD_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_USD_REBALANCE_THRESHOLD', '0'),
            ],
            'ZAR' => [
                'approved_receivable' => env('SWAP_ZAR_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_ZAR_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_ZAR_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_ZAR_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_ZAR_REBALANCE_THRESHOLD', '0'),
            ],
            'BTC' => [
                'approved_receivable' => env('SWAP_BTC_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_BTC_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_BTC_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_BTC_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_BTC_REBALANCE_THRESHOLD', '0'),
            ],
            'ETH' => [
                'approved_receivable' => env('SWAP_ETH_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_ETH_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_ETH_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_ETH_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_ETH_REBALANCE_THRESHOLD', '0'),
            ],
            'BNB' => [
                'approved_receivable' => env('SWAP_BNB_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_BNB_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_BNB_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_BNB_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_BNB_REBALANCE_THRESHOLD', '0'),
            ],
            'TRX' => [
                'approved_receivable' => env('SWAP_TRX_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_TRX_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_TRX_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_TRX_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_TRX_REBALANCE_THRESHOLD', '0'),
            ],
            'SOL' => [
                'approved_receivable' => env('SWAP_SOL_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_SOL_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_SOL_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_SOL_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_SOL_REBALANCE_THRESHOLD', '0'),
            ],
            'XRP' => [
                'approved_receivable' => env('SWAP_XRP_APPROVED_RECEIVABLE', '0'),
                'withdrawal_reserve' => env('SWAP_XRP_WITHDRAWAL_RESERVE', '0'),
                'minimum_inventory' => env('SWAP_XRP_MINIMUM_INVENTORY', '0'),
                'critical_inventory' => env('SWAP_XRP_CRITICAL_INVENTORY', '0'),
                'rebalance_threshold' => env('SWAP_XRP_REBALANCE_THRESHOLD', '0'),
            ],
        ],
    ],
];
