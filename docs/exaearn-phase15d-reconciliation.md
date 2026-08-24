# ExaEarn Phase 15D Reconciliation

`OtcRfqService::reconcile()` checks settled trades and settlements for missing ledger references.

Current reconciliation scope:

- settled trade has ledger reference
- settled settlement has ledger reference

Future expansion should compare RFQ, quote, reservation, execution legs, external receivable/payable, treasury and accounting records.
