# ExaEarn Phase 13 Reconciliation

`ExaAiReconciliationService` checks ExaAI portfolio accounting:

```text
available + reserved + deployed <= allocated
```

Critical differences are recorded in:

- `exaai_reconciliation_runs`
- `exaai_reconciliation_differences`

The service does not auto-correct money. Corrections must use existing ledger/settlement controls.
