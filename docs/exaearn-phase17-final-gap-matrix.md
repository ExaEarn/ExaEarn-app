# ExaEarn Phase 17 Final Gap Matrix

This matrix was produced during the Phase 17 final completion and hardening gate.

| Requirement | Current Implementation | Test Coverage | Status | Action Required |
| --- | --- | --- | --- | --- |
| Canonical ledger integration | Finance events are posted from canonical ledger transactions only. | Phase17 focused, Phase1 financial core, full backend. | READY | None. |
| No parallel balance system | Finance reads ledger/accounting records and asset-source attestations; it does not create a second user balance authority. | Phase17 focused, Phase1 financial core. | READY | None. |
| Double-entry accounting | Finance journals are balanced and linked to ledger references. | Balanced journal assertions and full backend. | READY | None. |
| Chart of accounts | Seeded finance chart maps customer liabilities, treasury assets, revenue, expenses, receivables, payables, escrow, staking, and suspense. | Phase17 focused. | READY | None. |
| Accounting rule engine | Ledger account classification maps entries into finance account codes and ownership classes. | Phase17 focused. | READY | Continue expanding mappings as new products launch. |
| Financial event engine | Idempotent financial events create journals once per source reference/event type. | Phase17 focused. | READY | None. |
| Journal immutability | Posted journals are preserved; corrections use adjustments/reopen workflows rather than rewriting posted history. | Adjustment and period-lock tests. | READY | None. |
| Customer vs corporate funds | Journal lines carry ownership class and backing excludes restricted/non-eligible assets. | Backing and journal assertions. | READY | None. |
| Liability engine | Customer liability aggregation comes from posted journal lines. | Backing and balance sheet tests. | READY | None. |
| Asset/backing engine | Asset sources, restrictions, freshness, eligible backing, coverage and break creation are implemented. | Backing deficit tests. | READY | Live sources remain external setup. |
| Product reconciliation | Finance product reconciliation delegates to existing product reconcilers and reports operational setup where a dependency is unavailable. | Phase17 focused and product regressions. | READY | Keep adding deeper product-specific reconciliation as each product matures. |
| Treasury accounting | Treasury position and PnL services aggregate treasury, revenue, expense and asset categories. | Phase17 focused. | READY | None. |
| Valuation engine | Valuation snapshots support reporting currency, valuation source and timestamp. | Phase17 focused and report snapshot coverage. | READY | Configure production price sources. |
| Revenue and expense accounting | Revenue/expense categories are represented in chart and P&L. | Monthly close/P&L test. | READY | None. |
| Accounts receivable/payable | Finance obligations support receivable/payable lifecycle and settlement status. | Phase17 focused. | READY | None. |
| Reconciliation breaks | Break engine records backing/data-quality issues without auto-correction. | Backing and data-quality tests. | READY | None. |
| Suspense accounting | Suspense account is mapped for adjustments and migration controls. | Adjustment/opening balance tests. | READY | None. |
| Financial adjustments | Maker-checker adjustment workflow posts canonical ledger and finance event entries. | Phase17 focused. | READY | None. |
| Daily close | Daily close produces trial balance, balance sheet, P&L, cash flow, backing snapshot, report snapshots, approval and locking. | Phase17 focused. | READY | None. |
| Monthly close | Monthly close is idempotent and produces the same report set and approval lock. | Monthly close test. | READY | None. |
| Period locking | Approved periods block unauthorized backdated finance postings; reopen requires separate checker. | Period-lock/reopen test. | READY | None. |
| Opening balance migration | Pending opening balance imports require maker-checker approval and post via canonical ledger. | Phase17 focused. | READY | None. |
| Financial statements | Trial balance, balance sheet, profit and loss, cash flow and general ledger services/routes are implemented. | Phase17 focused. | READY | None. |
| Admin Finance Center | Admin finance APIs expose overview, reports, reconciliation, treasury, obligations, opening balances, close controls and DLQ. | Phase17 focused plus admin typecheck/build. | READY | UI can continue to refine workflow ergonomics. |
| Auditor mode/RBAC | Finance permissions and hardened admin routes gate view/reconcile/adjust/close/export operations. | Phase17 focused. | READY | External auditors still require production account provisioning. |
| Finance DLQ | DLQ records and retry marking are implemented. | Phase17 focused. | READY | Wire production workers to send failed finance events to DLQ. |
| Data quality | Data-quality service detects missing/unposted/unbalanced finance artifacts and backing breaks. | Phase17 focused. | READY | None. |
| Restart recovery | Idempotent close/event/import/DLQ behavior is reconstructable from DB state after worker restart. | Idempotency tests and full backend. | READY | None. |
| Concurrency/failure injection | Maker-checker, idempotency, duplicate protection, period locks and financial invariants are tested. | Phase17 + Phase1 + full backend. | READY | Expand high-volume close tests before external audit. |
| Live external asset verification | Software supports explicit asset-source records and freshness/status checks. | Backing tests. | EXTERNAL | Configure production bank/custody/provider integrations. |
| Professional accounting policy approval | Software supports policy-compatible reports and controls. | Backend/admin tests. | EXTERNAL | Obtain accountant/auditor review. |

## Gate Conclusion

All software-controlled Phase 17 blockers identified by the final gate are implemented and covered by executable tests. The remaining items are external operational/accounting approvals and live provider configuration, not software blockers for beginning Phase 18.
