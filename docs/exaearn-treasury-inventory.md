# ExaEarn Treasury Inventory

`TreasuryInventoryService` reports controlled operational inventory by asset.

Tracked scopes:

- Ledger treasury/system accounts.
- External venue balances.
- Liquidity buckets.
- Withdrawal reserve state.

Buckets:

- `WITHDRAWAL_RESERVE`
- `CONVERT_INVENTORY`
- `SPOT_MARKET_MAKING`
- `EXTERNAL_ROUTING`
- `MARGIN_LENDING`
- `FUTURES_INSURANCE`
- `STAKING_OPERATIONS`
- `NETWORK_FEES`
- `CORPORATE_RESERVE`

Treasury buckets are operational allocation records. They do not replace the canonical ledger.
