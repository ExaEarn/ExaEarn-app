# ExaEarn Phase 15B Treasury Transfers

Institutional internal transfers are ledger-backed. They never mutate balances directly.

Transfer lifecycle:

```text
Request
→ permission check
→ idempotency check
→ threshold check
→ PENDING or PENDING_APPROVAL
→ maker-checker approval if required
→ BalanceProjectionService available-balance check
→ LedgerService double-entry posting
→ COMPLETED
```

Failure states include `FAILED`, `REJECTED`, and `CANCELLED`.

The transfer threshold is asset-specific through `institutional.large_transfer_threshold.{ASSET}`.

