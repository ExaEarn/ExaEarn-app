# ExaEarn Phase 15F Reconciliation

`Phase15ReconciliationService` creates persisted reconciliation runs and differences.

## Checks

- LIVE listing applications without LIVE markets.
- Active Phase 15 markets without listing configuration when marked as Phase 15 listed.
- Active market-maker assignments without active profiles.
- Active/approved bots without active market makers.
- Active/approved bots without active market assignments.
- Settled OTC trades/settlements without ledger references.
- Expired active reservations.

## Behavior

Reconciliation reports differences only. It does not silently repair financial state or fabricate corrections.
