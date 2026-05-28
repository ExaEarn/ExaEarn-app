# Treasury Engine - Implementation Guide

## Quick Start

### 1. Database Setup

```bash
# Run migrations
php artisan migrate

# Seed example treasury wallets (for development)
php artisan db:seed --class=TreasurySeeder
```

### 2. Environment Configuration

Add to `.env`:

```bash
# Treasury encryption key (32 characters)
TREASURY_KEY_SECRET=your-32-character-key-for-key-encryption

# Node.js blockchain watcher service
SERVICES_NODE_URL=http://localhost:3000
SERVICES_NODE_WEBHOOK_SECRET=your-webhook-secret-token

# Queue configuration
QUEUE_CONNECTION=redis
```

### 3. Production Wallet Setup

After seeding, update wallet addresses and keys via admin API:

**Update hot wallet private key:**
```bash
POST /api/admin/settings/treasury/wallets/{id}/update-key
Content-Type: application/json

{
  "private_key": "your-unencrypted-private-key"
}
```

**Update wallet address:**
```bash
POST /api/admin/settings/treasury/wallets/{id}/update-address
Content-Type: application/json

{
  "address": "0x742d35Cc6634C0532925a3b844Bc454e4438f44e"
}
```

**Update treasury configuration:**
```bash
POST /api/admin/settings/treasury/config
Content-Type: application/json

{
  "withdrawal_rules": {
    "auto_threshold": 100,
    "risk_check_threshold": 1000,
    "admin_threshold": 5000,
    "cold_wallet_threshold": 10000,
    "manual_threshold": 50000
  },
  "asset_usd_rates": {
    "USDT": 1.0,
    "USDC": 1.0,
    "BTC": 50000.0,
    "ETH": 3000.0
  },
  "hot_wallet": {
    "max_balance": 100000,
    "min_balance": 1000
  }
}
```

### 4. Start Monitoring Wallets

After configuring wallets, enable blockchain monitoring:

```bash
POST /api/admin/treasury/monitoring/watch
Content-Type: application/json

{
  "wallet_id": 1
}
```

## API Reference

### Wallet Management

**List Treasury Wallets:**
```bash
GET /api/admin/treasury/wallets
Authorization: Bearer {token}
```

**Create Treasury Wallet:**
```bash
POST /api/admin/treasury/wallets
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "hot",
  "chain": "ethereum",
  "address": "0x...",
  "label": "Main Hot Wallet",
  "metadata": {}
}
```

### Monitoring

**Get Monitoring Status:**
```bash
GET /api/admin/treasury/monitoring/status
Authorization: Bearer {token}
```

**Get Treasury Health:**
```bash
GET /api/admin/treasury/monitoring/health
Authorization: Bearer {token}
```

### Fund Management

**Get Treasury Balances:**
```bash
GET /api/admin/treasury/balances
Authorization: Bearer {token}
```

**Move to Cold Wallet:**
```bash
POST /api/admin/treasury/move-to-cold
Authorization: Bearer {token}
Content-Type: application/json

{
  "chain": "ethereum",
  "asset": "USDT",
  "amount": "50000"
}
```

**Move to Hot Wallet:**
```bash
POST /api/admin/treasury/move-to-hot
Authorization: Bearer {token}
Content-Type: application/json

{
  "chain": "ethereum",
  "asset": "USDT",
  "amount": "10000"
}
```

### Withdrawal Workflow

**List Withdrawal Requests:**
```bash
GET /api/admin/treasury/withdraw-requests?status=pending
Authorization: Bearer {token}
```

**Approve Withdrawal:**
```bash
POST /api/admin/treasury/withdraw-requests/{id}/approve
Authorization: Bearer {token}
```

**Sign & Send Withdrawal:**
```bash
POST /api/admin/treasury/withdraw-requests/{id}/sign
Authorization: Bearer {token}
```

**View Treasury Transactions:**
```bash
GET /api/admin/treasury/transactions?chain=ethereum&type=deposit
Authorization: Bearer {token}
```

### Deposit Webhook

The Node.js blockchain watcher calls this endpoint:

```bash
POST /webhooks/treasury-deposits
Content-Type: application/json
Authorization: Bearer {webhook-secret}

{
  "chain": "ethereum",
  "asset": "USDT",
  "from_address": "0x1234...",
  "to_address": "0x5678...",
  "amount": "100.50",
  "tx_hash": "0xabcd...",
  "block_number": 18500000,
  "confirmations": 12
}
```

## Testing

**Run treasury tests:**
```bash
# Test deposit workflow
php artisan treasury:test --deposit

# Test fund movement
php artisan treasury:test --move

# Test withdrawal workflow
php artisan treasury:test --withdraw
```

## Monitoring & Debugging

**View treasury logs:**
```bash
tail -f storage/logs/laravel.log | grep treasury
```

**Check queue status:**
```bash
php artisan queue:work --queue=treasury
```

**Monitor sweep jobs:**
```bash
php artisan queue:work --queue=treasury --tries=3
```

## Security Checklist

- [ ] Set `TREASURY_KEY_SECRET` environment variable (32+ characters)
- [ ] Configure production wallet addresses via admin API
- [ ] Set up encrypted private keys for hot wallets
- [ ] Configure withdrawal thresholds and approval rules
- [ ] Enable blockchain watcher monitoring
- [ ] Set up queue workers for sweep/sign jobs
- [ ] Configure alerts for large transactions
- [ ] Regularly rotate private keys (every 90 days)
- [ ] Review audit logs for suspicious activity
- [ ] Test withdrawal workflow end-to-end

## Troubleshooting

### Private Key Decryption Failed
- Check `TREASURY_KEY_SECRET` is set correctly
- Verify key was encrypted with the same secret
- Use `php artisan tinker` to debug

### Deposits Not Being Processed
- Verify Node.js watcher is running and connected
- Check watcher status: `GET /api/admin/treasury/monitoring/status`
- Review webhook logs: `POST /webhooks/treasury-deposits`

### Withdrawal Stuck
- Check job queue: `php artisan queue:failed`
- Verify hot wallet has sufficient balance
- Review approval status: `GET /api/admin/treasury/withdraw-requests`

### Unable to Connect to Node Service
- Verify `SERVICES_NODE_URL` is correct
- Check Node.js service is running
- Test connection: `curl http://localhost:3000/health`