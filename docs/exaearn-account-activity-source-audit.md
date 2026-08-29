# ExaEarn Account Activity Source Audit

Account Activity is backed by the existing immutable `activity_logs` table and `ActivityAuditService`.

## Sources

- Auth and security events: `ActivityAuditService::logAuth` and `logSecurity`.
- Wallet and money movement: `logWallet`.
- Trading: `logTrade`.
- Earn and rewards: `logStaking` and `logReward`.
- NFT/ecosystem: `logNft`.
- Admin/system: `logAdmin` and `logSystem`.

## Policy

Activity is a chronological explanation layer over authoritative product records. It is not a wallet, ledger, order, position, or settlement source of truth. Reading, archiving or deleting notifications does not remove Activity records.
