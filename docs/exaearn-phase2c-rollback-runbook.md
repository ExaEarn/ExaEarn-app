# ExaEarn Phase 2C Rollback Runbook

Rollback is explicit. The system must not silently restore legacy authority just because the new engine has an error.

## Rollback Triggers

- ownership conflict
- fencing failure
- sequence gap
- journal unavailable
- snapshot corruption
- replay failure
- duplicate execution
- settlement backlog above threshold
- ledger imbalance
- orphan reservation
- order-book invariant failure
- critical realtime sequence inconsistency

## Procedure

1. Set market `ROLLBACK_PENDING`.
2. Reject new orders.
3. Halt matching.
4. Finish or manually review authoritative settlements.
5. Persist final snapshot.
6. Run replay and ledger reconciliation.
7. Cancel/release new-engine open orders unless a proven migration policy exists.
8. Mark market `ROLLBACK_ONLY`.
9. Verify no duplicate authority remains.
10. Restore `LEGACY` only after explicit operator approval.

## Implemented Command

```bash
php artisan spot:cutover {market} rollback --reason="operator reason"
```

