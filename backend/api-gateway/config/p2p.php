<?php

declare(strict_types=1);

return [
    'supported_assets' => array_filter(array_map('trim', explode(',', (string) env('P2P_SUPPORTED_ASSETS', 'USDT,USDC,BTC,ETH,SOL,XRP,EXA')))),
    'supported_fiat' => array_filter(array_map('trim', explode(',', (string) env('P2P_SUPPORTED_FIAT', 'NGN,USD,GHS,KES,ZAR')))),
    'supported_payment_methods' => array_filter(array_map('trim', explode(',', (string) env('P2P_SUPPORTED_PAYMENT_METHODS', 'Bank Transfer,Opay,Airtel Money,PalmPay')))),
    'merchant_badge_min_completed_trades' => (int) env('P2P_MERCHANT_BADGE_MIN_COMPLETED_TRADES', 25),
    'new_user_trade_limit' => (string) env('P2P_NEW_USER_TRADE_LIMIT', '100'),
    'fees' => [
        'maker' => (string) env('P2P_MAKER_FEE_RATE', '0'),
        'taker' => (string) env('P2P_TAKER_FEE_RATE', '0'),
        'merchant' => (string) env('P2P_MERCHANT_FEE_RATE', '0'),
    ],
    'payment_verification_mode' => (string) env('P2P_PAYMENT_VERIFICATION_MODE', 'manual_only'),
    'merchant_operations_status' => (string) env('P2P_MERCHANT_OPERATIONS_STATUS', 'not_staffed'),
    'dispute_operations_status' => (string) env('P2P_DISPUTE_OPERATIONS_STATUS', 'not_staffed'),
    'compliance_approval' => (string) env('P2P_COMPLIANCE_APPROVAL', 'required'),
    'release_due_minutes' => (int) env('P2P_RELEASE_DUE_MINUTES', 30),
    'dispute_window_minutes' => (int) env('P2P_DISPUTE_WINDOW_MINUTES', 60),
    'max_recent_cancellations' => (int) env('P2P_MAX_RECENT_CANCELLATIONS', 5),
    'max_recent_disputes' => (int) env('P2P_MAX_RECENT_DISPUTES', 3),
    'chat_flag_keywords' => array_filter(array_map('trim', explode(',', (string) env(
        'P2P_CHAT_FLAG_KEYWORDS',
        'whatsapp,telegram,off-platform,external deal,send outside,cashapp,crypto first'
    )))),
];
