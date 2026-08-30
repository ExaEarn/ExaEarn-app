# ExaEarn Non-Trading Reconciliation Standard

Every money-moving product must expose or participate in reconciliation comparing product records, reservations, ledger entries, balance projections, accounting events and external provider/chain state where applicable.

## States

- `MATCHED`: product and financial records reconcile.
- `WARNING`: non-critical mismatch or stale operational state.
- `BREAK`: financial/product mismatch requiring review before further action.
- `CRITICAL`: mismatch that may require product safe mode, payout/funding blocks or incident escalation.

## Required Fields

```text
product
date/window
asset
expected
actual
difference
reason
entity/reference
status
severity
created_at
resolved_at
resolution
```

## Recovery Rule

Material unexplained differences must not be silently corrected. They require an auditable reconciliation finding, break or incident before any compensating transaction is posted.

