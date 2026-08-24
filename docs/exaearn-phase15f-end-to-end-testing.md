# ExaEarn Phase 15F End-to-End Testing

Focused coverage was added in `Phase15FInstitutionalLiquidityIntegrationTest`.

## Covered

- Listing market readiness blocks underfunded liquidity.
- Funded market-maker subaccount satisfies launch readiness.
- Readiness requires configured listing, market, liquidity requirement, assignment and capital.
- Revoked developer API keys block bot quoting.
- Reconciliation detects active bot with paused market-maker profile.
- Emergency market halt propagates to market, bot and OTC configuration.

## Regression

Phase 15E market-maker bot regression is rerun because Phase 15F strengthens bot risk checks.
