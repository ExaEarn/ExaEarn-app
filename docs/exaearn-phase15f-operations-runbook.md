# ExaEarn Phase 15F Operations Runbook

## Market Launch

1. Confirm listing application is approved.
2. Confirm asset and market configurations exist.
3. Confirm latest listing test run passes.
4. Confirm liquidity requirement is configured.
5. Confirm at least one market maker is assigned and capital-ready.
6. Confirm bot readiness and API key status.
7. Use admin launch readiness endpoint.
8. Proceed with launch only through the approved market launch process.

## Liquidity Emergency

1. Trigger Phase 15 emergency control for `MARKET` or `GLOBAL`.
2. Confirm affected market status is halted.
3. Confirm affected bots are paused and mass-cancel executed.
4. Confirm OTC market configuration is disabled.
5. Run reconciliation.
6. Resume only after root cause and reconciliation are resolved.

## Reconciliation Break

1. Review `phase15_reconciliation_differences`.
2. Classify severity and owner.
3. Do not manually change balances.
4. Use product-specific correction or ledger reversal workflows when financial correction is required.
