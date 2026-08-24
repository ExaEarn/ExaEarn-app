# ExaEarn Phase 2B Production Cutover

Phase 2B supports controlled market-by-market spot-engine cutover.

## Current Feature Flag

`TRADING_ENGINE_MODE=new` routes spot order placement through the Phase 2 OMS and matching engine.

Default behavior should remain conservative until cutover:

- `legacy`: legacy path
- `shadow`: compare legacy and new outputs without user-facing authority
- `new`: new OMS/matcher authority

## Required Cutover Steps

1. Confirm Phase 1 financial core migrations are deployed.
2. Confirm Phase 2 spot engine migrations are deployed.
3. Confirm Phase 2B authority migrations are deployed.
4. Run `php artisan migrate:status`.
5. Run focused Phase 1/2/2B tests.
6. Run `spot:load-harness` against the target staging database.
7. Run `spot:replay` for every candidate market.
8. Confirm settlement outbox metrics are healthy.
9. Run shadow comparison for the observation window.
10. Resolve all `UNRESOLVED` shadow comparisons.
11. Enable `TRADING_ENGINE_MODE=new` for one low-risk market.
12. Monitor leases, sequence gaps, settlement outbox, ledger reconciliation and API errors.
13. Expand market-by-market only after the previous market remains healthy.

## Rollback

Rollback must not delete journal, ledger, trade, order or settlement rows.

If authority must move back to legacy:

1. Disable new order entry for the affected market.
2. Drain or halt open order handling according to operations policy.
3. Process or manually review settlement outbox rows.
4. Run replay and ledger reconciliation.
5. Set `TRADING_ENGINE_MODE=legacy`.
6. Keep Phase 2B tables for audit and recovery.

## Production Status

The Phase 2B gate is ready for controlled spot market cutover. It is not authorization to begin Futures, Margin, FIX, institutional APIs or Phase 3 work.

