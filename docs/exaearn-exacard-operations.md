# ExaCard Operations

## Runbook

Provider funding failed:

1. Verify funding request status is `FAILED`.
2. Confirm the related reservation is `RELEASED`.
3. Confirm no `card_funding` ledger transaction was posted.

Provider funding unknown:

1. Verify funding request status is `PROVIDER_UNKNOWN`.
2. Confirm the related reservation remains `ACTIVE`.
3. Reconcile directly with the provider.
4. Settle or release only through the controlled provider/reconciliation workflow.

Chargeback created:

1. Verify signed webhook was processed once.
2. Review `card_disputes`.
3. Submit evidence through the provider operating process.
4. Keep finance adjustments auditable.

Provider liquidity low:

1. Review `/api/admin/v1/exacard/treasury`.
2. Treat `REBALANCE_REQUIRED` as a treasury action.
3. Rebalance provider funds through approved treasury controls.

