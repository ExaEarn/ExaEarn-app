# ExaEarn Treasury Engine

The Treasury Engine provides secure, automated management of cryptocurrency funds with hot/cold wallet architecture, deposit sweeping, and admin-controlled withdrawals.

## Features

- **Hot/Cold Wallet Architecture**: Secure fund storage with operational hot wallets and secure cold wallets
- **Automated Deposit Sweeping**: Automatically moves user deposits to hot wallets for liquidity
- **Multi-Threshold Withdrawals**: Configurable approval workflows for different withdrawal amounts
- **Encrypted Key Management**: Secure storage of private keys with encryption
- **Blockchain Integration**: Webhook-based deposit notifications from blockchain watchers
- **Admin Dashboard**: Complete treasury management interface

## Setup

### 1. Environment Variables

Add to your `.env` file:

```bash
TREASURY_KEY_SECRET=your-32-character-secret-key-here
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Seed Treasury Wallets

```bash
php artisan db:seed --class=TreasurySeeder
```

⚠️ **IMPORTANT**: After seeding, update the wallet addresses and private keys with real values through the admin interface.

### 4. Configure Treasury Settings

Use the admin API to configure:

- Wallet addresses and encrypted private keys
- Withdrawal thresholds and approval rules
- Asset USD rates for risk assessment

## API Endpoints

### Admin Treasury Management

All endpoints require `role:admin` middleware.

#### Wallets
- `GET /api/admin/treasury/wallets` - List all treasury wallets
- `POST /api/admin/treasury/wallets` - Create new treasury wallet

#### Balances
- `GET /api/admin/treasury/balances` - Get treasury balances across chains

#### Fund Movement
- `POST /api/admin/treasury/move-to-cold` - Move funds from hot to cold wallet
- `POST /api/admin/treasury/move-to-hot` - Move funds from cold to hot wallet

#### Withdrawals
- `GET /api/admin/treasury/withdraw-requests` - List withdrawal requests
- `POST /api/admin/treasury/withdraw-requests/{id}/approve` - Approve withdrawal
- `POST /api/admin/treasury/withdraw-requests/{id}/sign` - Sign and send withdrawal

#### Transactions
- `GET /api/admin/treasury/transactions` - List treasury transactions

### Treasury Settings
- `GET /api/admin/settings/treasury` - Get treasury configuration
- `POST /api/admin/settings/treasury/config` - Update treasury settings
- `POST /api/admin/settings/treasury/wallets/{id}/update-key` - Update wallet private key
- `POST /api/admin/settings/treasury/wallets/{id}/update-address` - Update wallet address

### Webhooks
- `POST /webhooks/treasury-deposits` - Receive deposit notifications from blockchain watchers

## Configuration

The treasury system is configured via `config/treasury.php`:

```php
return [
    'withdrawal_rules' => [
        'auto_threshold' => 100,      // Auto-approve under $100
        'risk_check_threshold' => 1000, // Risk check $100-$1000
        'admin_threshold' => 5000,    // Admin approval $1000-$5000
        'cold_wallet_threshold' => 10000, // Cold wallet approval $5000-$10000
        'manual_threshold' => 50000,  // Manual review over $50000
    ],
    'asset_usd_rates' => [
        'USDT' => 1.0,
        'USDC' => 1.0,
        'BTC' => 50000.0,
        'ETH' => 3000.0,
    ],
    'hot_wallet' => [
        'max_balance' => 100000,      // Max balance before moving to cold
        'min_balance' => 1000,        // Min balance to maintain
    ],
    'security' => [
        'encrypt_keys' => true,
        'key_secret' => env('TREASURY_KEY_SECRET'),
        'key_rotation_days' => 90,
    ],
];
```

## Workflow

### Deposit Processing
1. User sends crypto to deposit address
2. Blockchain watcher detects transaction
3. Webhook notifies `/webhooks/treasury-deposits`
4. If deposit to non-hot wallet, `SweepFundsJob` sweeps to hot wallet
5. Funds become available for trading/withdrawals

### Withdrawal Processing
1. User requests withdrawal via `/api/wallet/withdraw`
2. System checks withdrawal limits and applies appropriate approval level
3. Admin approves via `/api/admin/treasury/withdraw-requests/{id}/approve`
4. Admin signs via `/api/admin/treasury/withdraw-requests/{id}/sign`
5. `SignWithdrawalJob` processes the transaction on blockchain

### Fund Management
- Hot wallets maintain operational liquidity
- Excess funds automatically moved to cold storage
- Admin can manually move funds between hot/cold wallets

## Testing

Run the test command to validate components:

```bash
# Test deposit workflow
php artisan treasury:test --deposit

# Test fund movement
php artisan treasury:test --move

# Test withdrawal workflow (requires setup)
php artisan treasury:test --withdraw
```

## Security Considerations

- Private keys are encrypted using Laravel's `Crypt` facade
- All treasury operations are logged with admin user context
- Multi-signature requirements for large withdrawals
- IP and user agent logging for audit trails
- Configurable withdrawal thresholds prevent unauthorized large transfers

## Integration with Blockchain Watcher

The Node.js blockchain watcher should call the treasury deposit webhook with:

```json
{
  "chain": "ethereum",
  "asset": "USDT",
  "from_address": "0x...",
  "to_address": "0x...",
  "amount": "100.50",
  "tx_hash": "0x...",
  "block_number": 18500000,
  "confirmations": 12
}
```

## Monitoring

Monitor treasury health through:
- Admin dashboard balances
- Transaction logs
- Queue status for sweep/sign jobs
- Alert on failed transactions or stuck queues