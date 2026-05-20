<?php

declare(strict_types=1);

return [
    'chain' => env('GAMEFI_CHAIN', 'base'),
    'contract_enabled' => filter_var(env('GAMEFI_CONTRACT_ENABLED', true), FILTER_VALIDATE_BOOL),
    'min_wallet_age_days' => (int) env('GAMEFI_MIN_WALLET_AGE_DAYS', 1),
    'max_entries_per_wallet' => (int) env('GAMEFI_MAX_ENTRIES_PER_WALLET', 10),
    'max_bets_per_wallet' => (int) env('GAMEFI_MAX_BETS_PER_WALLET', 20),
];
