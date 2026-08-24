# ExaEarn Phase 15A Asset Registry

Listing automation reuses the existing custody asset registry through `blockchain_assets`.

New assets default to:

- `deposit_enabled = false`
- `withdrawal_enabled = false`
- `trading_enabled = false` on listing configuration

The listing system does not credit balances. Deposits continue through custody detection and canonical ledger settlement.

