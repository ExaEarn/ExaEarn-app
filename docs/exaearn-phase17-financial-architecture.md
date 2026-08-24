# ExaEarn Phase 17 Financial Architecture

Architecture:

`Product service -> canonical ledger -> finance event -> journal -> reports/reconciliation/backing`

Phase 17 tables are reporting/control-plane tables. They do not replace:
- `accounts`
- `ledger_transactions`
- `ledger_entries`
- `reservations`

Key services:
- `FinanceAccountingService`
- `FinanceBackingService`
- `FinanceReportService`
- `FinanceAdjustmentService`
- `FinanceCloseService`
- `FinanceReadinessService`
- `FinanceValuationService`

Financial events and journals remain linked to canonical ledger transactions where economic movement occurred.
