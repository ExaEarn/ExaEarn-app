# ExaEarn Phase 17 Accounting Engine

`FinanceAccountingService` records idempotent finance events and posts journals from canonical ledger entries.

Rules:
- One ledger transaction and event type create one finance event.
- Posted journal lines are generated from immutable ledger entries.
- Journal debit/credit totals must balance per asset.
- Customer ledger accounts map to liability accounts.
- System treasury/provider/revenue accounts map to asset/revenue/expense/payable classes.

Corrections must be new ledger/journal activity, not silent edits.
