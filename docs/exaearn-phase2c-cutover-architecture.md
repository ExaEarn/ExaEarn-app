# ExaEarn Phase 2C Cutover Architecture

Phase 2C moves Spot trading from a global matcher switch to per-market authority.

## Reused Infrastructure

- Phase 1 canonical ledger
- `ReservationService`
- `SettlementService`
- `BalanceProjectionService`
- Phase 2 OMS and matching engine
- Phase 2B market leases, replay, realtime sequence log, shadow comparison and settlement outbox

## Added Infrastructure

- Market columns:
  - `engine_mode`
  - `cutover_state`
  - `health_status`
  - `engine_mode_updated_at`
- `spot_cutover_journals`
- `SpotEngineModeResolver`
- `SpotCutoverReadinessService`
- `SpotCutoverService`
- cutover operator commands

## Per-Market Authority

Each market can be independently configured as:

- `legacy`
- `shadow`
- `new`
- `halted`
- `rollback_only`

Unknown or unconfigured markets default to `legacy`.

## Server-Side Enforcement

`TradeService` resolves the market before order placement and cancellation. The frontend cannot choose the engine.

- `new`: route to Phase 2 OMS.
- `legacy`: route to legacy matcher.
- `shadow`: legacy remains financially authoritative.
- `halted`: reject order entry.
- `rollback_only`: legacy is available only as explicit rollback authority.

The legacy matcher refuses to execute if the market is not legacy-authoritative.

