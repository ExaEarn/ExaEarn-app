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
];
