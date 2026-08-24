# ExaEarn Financial Reconciliation

`FinancialReconciliationService` creates a unified reconciliation run.

## Sources

- `LedgerReconciliationService`
- `MarginReconciliationService`
- `FuturesReconciliationService`
- `SwapReconciliationService`
- lending-pool invariants
- insurance-fund balance checks

## Persistence

- `financial_reconciliation_runs`
- `financial_reconciliation_differences`

## Status

- `PASS`
- `FAIL`
- `CRITICAL`

Critical differences can be surfaced to readiness and admin operations.

