# ExaEarn Phase 7 Preimplementation Audit

## Scope

This audit reviewed the existing ExaEarn backend after Phase 6B readiness passed. The goal was to identify the operational controls required before running Spot, Futures, Margin, Convert, treasury and accounting as one controlled exchange system.

## Existing Capabilities

- Canonical ledger, reservations, settlement and balance projections exist from Phase 1.
- Spot OMS, sequencer, matching engine, execution journal, settlement outbox and cutover controls exist from Phase 2/2B/2C.
- Authoritative internal market data, tickers, order books, trades, candles and public market routes exist from Phase 3.
- Convert quotes, backing checks, reservations, settlement and reconciliation exist from Phase 4.
- Futures risk, index/mark price services, funding, insurance fund and liquidation foundations exist from Phase 5/5B.
- Margin accounts, borrow/repay, lending pools, Auto Borrow, Auto Repay, liquidation execution, realtime replay and readiness checks exist from Phase 6/6B.
- Admin security middleware, admin audit middleware, Sanctum auth and rate-limit middleware already exist.

## Missing Controls Before Phase 7

- No single trading risk engine covered Spot, Futures and Margin admission.
- No central circuit breaker model existed for global/product/market emergency states.
- Market pause was not backed by a durable operational control record.
- Price protection was fragmented and not exposed as a reusable pre-trade service.
- Collateral haircut rules existed inside product services but were not centrally versioned for operations.
- Reconciliation existed per subsystem but not as a unified financial reconciliation run.
- Insurance fund had Futures-specific behavior but no general operational ledger/read model.
- Negative-equity detection was not centralized into incident creation and bad-debt workflow.
- Admin operations had no single readiness endpoint for trading systems.
- Load probes existed in individual areas but not as a unified trading operations probe.
- Operational incident records and action history were missing.

## Unsafe or Incomplete Logic Found

- Pre-trade risk checks were product-local and could diverge across Spot, Futures and Margin.
- Using `last_price` as a risk anchor for new markets can be unsafe if the market is still bootstrapping; Phase 7 now requires a trusted anchor before applying deviation rejection.
- Repayment splitting could encounter tiny timing/rounding differences when interest accrual occurs near settlement; Phase 7 added a final deterministic balancing adjustment.
- Margin and Futures liquidation foundations were present, but operational readiness needed a central view.

## Race/Restart Risks

- Financial writes are already transaction-backed in migrated paths.
- Realtime replay exists for Margin and Spot public data, but a full private event bus across every product remains an expansion area.
- Queue/dead-letter monitoring exists only partially; Phase 7 adds readiness/load records but not a full queue observability stack.

## Phase 7 Implementation Direction

Incrementally add an operations/risk control plane:

- durable risk/circuit/incident/readiness/reconciliation tables
- central `TradingRiskEngine`
- local-only `PriceProtectionService`
- admin operational control APIs
- unified reconciliation and readiness services
- regression tests proving fail-closed behavior

