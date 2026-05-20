<?php

declare(strict_types=1);

return [
    'merchant_badge_min_completed_trades' => (int) env('P2P_MERCHANT_BADGE_MIN_COMPLETED_TRADES', 25),
    'new_user_trade_limit' => (float) env('P2P_NEW_USER_TRADE_LIMIT', 100),
    'max_recent_cancellations' => (int) env('P2P_MAX_RECENT_CANCELLATIONS', 5),
    'max_recent_disputes' => (int) env('P2P_MAX_RECENT_DISPUTES', 3),
    'chat_flag_keywords' => array_filter(array_map('trim', explode(',', (string) env(
        'P2P_CHAT_FLAG_KEYWORDS',
        'whatsapp,telegram,off-platform,external deal,send outside,cashapp,crypto first'
    )))),
];
