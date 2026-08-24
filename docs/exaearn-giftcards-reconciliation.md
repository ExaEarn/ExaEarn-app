# ExaEarn Giftcards Reconciliation

`GiftCardReconciliationService` performs Giftcard-specific checks:

- completed/delivered buy orders must have a completed canonical ledger settlement
- delivered inventory must not show duplicate delivery references
- findings are reported, not silently repaired

Provider-unknown orders retain reservations until operator/provider reconciliation determines whether to complete settlement, release funds, or escalate.

