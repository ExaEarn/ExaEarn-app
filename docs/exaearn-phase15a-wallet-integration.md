# ExaEarn Phase 15A Wallet Integration

Listing automation does not create a separate wallet system.

Flow:

`Listing asset/network configuration -> blockchain_assets -> existing custody adapters -> deposit detection -> canonical ledger -> withdrawal engine`

Scheduled deposit and withdrawal opening updates the listing network configuration and the canonical custody asset flags. It does not mutate user balances.

