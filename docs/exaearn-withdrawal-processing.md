# ExaEarn Withdrawal Processing

Crypto withdrawals use the new `custody_withdrawals` lifecycle.

## Lifecycle

- `REQUESTED`
- `VALIDATING`
- `RISK_REVIEW`
- `APPROVAL_REQUIRED`
- `APPROVED`
- `BALANCE_RESERVED`
- `QUEUED`
- `BUILDING`
- `SIGNING`
- `SIGNED`
- `BROADCASTING`
- `BROADCASTED`
- `CONFIRMING`
- `COMPLETED`
- `FAILED`
- `CANCELLED`
- `MANUAL_REVIEW`
- `LIQUIDITY_REBALANCE_REQUIRED`

## Controls

- Idempotency key required per user.
- Destination address is validated by network provider.
- XRP destination tag is required by network policy.
- Funds are reserved before transaction construction.
- Broadcasted withdrawals are not marked completed until configured finality is reached.
- Completion posts `SettlementService::custodyWithdrawal()` to account for user amount, network fee, and platform fee separately.

## Duplicate Prevention

- Unique `user_id + idempotency_key`
- Unique signing request hash
- Unique broadcast tracking
- Canonical reservation consumption
- Ledger reference uniqueness
