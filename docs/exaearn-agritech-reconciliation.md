# ExaEarn AgriTech Reconciliation

`AgriTechReconciliationService` reports discrepancies and never silently repairs them. Current checks cover:

- share totals versus available, reserved and allocated quantities;
- settled investments missing canonical ledger transactions;
- harvest allocation totals versus recorded net distributable amount;
- failed or incomplete payout state.

Findings are persisted with severity, expected value, actual value, reference and status. Finance operations must investigate differences and use canonical correction or reversal workflows.
