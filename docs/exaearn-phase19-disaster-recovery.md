# ExaEarn Phase 19 Disaster Recovery

Recovery order:

1. Freeze unsafe writes if required.
2. Restore database or fail over to healthy primary.
3. Restore Redis/cache/realtime.
4. Restart workers and schedulers.
5. Rebuild/read market-data projections where needed.
6. Run ledger and finance reconciliation.
7. Run security/compliance readiness.
8. Resume in safe mode.
9. Return to normal only after validation.

Financial corrections must use existing ledger/reconciliation workflows. Silent repair is not allowed.

