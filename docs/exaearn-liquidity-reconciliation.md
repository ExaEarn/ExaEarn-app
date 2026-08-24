# ExaEarn Liquidity Reconciliation

`LiquidityReconciliationService` checks Phase 8 invariants:

- External venue reserved balance must not exceed available balance.
- Treasury bucket reserved amount must not exceed allocated amount.
- Withdrawal reserves below minimum are critical findings.
- Active liquidity reservations must not have negative remaining amounts.

Results are stored in:

- `liquidity_reconciliation_runs`
- `liquidity_reconciliation_differences`

The service reports differences and does not silently correct them.
