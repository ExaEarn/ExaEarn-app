# ExaEarn Custody Reconciliation

`CustodyReconciliationService` compares:

- user liabilities from canonical accounts
- controlled backing from custody wallet balance snapshots
- pending deposits
- pending withdrawals
- network-fee reserves

It persists:

- `custody_reconciliation_runs`
- `custody_reconciliation_differences`
- `custody_daily_snapshots`

The service reports discrepancies but does not silently correct balances.

## Backing Coverage

```text
coverage_ratio = controlled_backing / user_liabilities
```

Restricted, external, staked, and pending balances must be reported separately as custody data matures.
