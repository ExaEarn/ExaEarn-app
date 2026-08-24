# ExaEarn Phase 17 Financial Close

`FinanceCloseService` supports daily and monthly close preparation.

Preparation captures:
- Trial balance state
- Backing state
- Period range
- Reporting currency

Approval requires segregation of duties: the preparer cannot approve the same close period.

Approved periods are marked `APPROVED_LOCKED`.
