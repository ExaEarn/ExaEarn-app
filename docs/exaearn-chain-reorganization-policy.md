# ExaEarn Chain Reorganization Policy

Deposits must not be credited until required confirmations are reached.

If a previously observed block hash changes:

1. Mark the deposit `REORG_PENDING`.
2. Prevent automatic credit.
3. Preserve the original deposit record.
4. Create an audit event.
5. Require operational review.

If an exceptional reorg invalidates an already credited deposit, operations must use the ledger reversal/correction workflow; the original ledger transaction must remain immutable.
