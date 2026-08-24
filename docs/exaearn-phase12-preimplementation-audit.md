# ExaEarn Phase 12 Preimplementation Audit

## Scope

Phase 12 inspected the existing advanced futures and copy-trading surface after Phases 7-11. The goal was to reuse the existing Futures OMS, risk engine, reservation service, ledger, market controls, and admin security instead of creating a parallel trading stack.

## Findings

| Area | Current State | Classification | Decision |
| --- | --- | --- | --- |
| Futures OMS | `FuturesOrderService` supports market, limit, stop-market, stop-limit and trailing-stop order types. | KEEP / HARDEN | Reuse as the execution path for follower copy orders. |
| Reduce-only | Enforced by `FuturesRiskEngineService::validateReduceOnly`. | KEEP | Copy close/reduce events are submitted as reduce-only. |
| Post-only | Enforced by `FuturesOrderService`; post-only rejects taking orders. | KEEP | No separate post-only implementation required. |
| Conditional orders | Existing conditional order fields and trigger path are present. | KEEP / HARDEN LATER | Phase 12 did not replace the working conditional path. |
| Cross/isolated margin | Risk service checks cross/isolated account availability. | KEEP | Copy followers can choose isolated, cross or follow lead preference. |
| Funding | Existing funding engine is present from Phase 5. | KEEP | No duplicate funding engine created. |
| Liquidation / insurance / ADL | Existing Phase 5/5B services exist. | KEEP | Copy trading uses normal follower futures positions/orders, so downstream risk remains per follower. |
| Legacy copy trading service | Existing service copied payloads through an unsafe call shape and used weak production semantics. | REPLACE | Replaced with an event-driven lead-execution fanout service. |
| Lead trader model | `traders` table existed without a model and lacked production approval/profile fields. | HARDEN | Added `Trader` model and additive schema fields. |
| Copy relationships | Existing table tracked only follower/trader/allocation/risk/status. | HARDEN | Added product scope, copy mode, limits, leverage cap, symbol allowlist, copy balances, and high-water mark. |
| Copy execution idempotency | Missing durable lead execution and copy order idempotency. | HARDEN | Added `copy_lead_trade_events` and `copy_orders` with unique event/order constraints. |
| Strategy attribution | Missing virtual strategy position ledger. | HARDEN | Added `copy_strategy_positions`. |
| Profit-share | Missing high-water-mark accrual records. | HARDEN | Added `copy_profit_share_accruals` and service accrual logic. |
| Surveillance | No copy-trading-specific surveillance record. | HARDEN | Added `copy_surveillance_events` for later automated/manually generated alerts. |
| Spot copy | No safe production route to spot OMS was confirmed in this Phase 12 slice. | DEFER | Spot lead events are recorded/skipped with `PRODUCT_NOT_SUPPORTED` instead of fabricated. |
| Realtime fanout | Existing realtime exists, but copy-specific private replay was not fully wired in this slice. | DEFER | Database event model is ready for replay and private stream integration. |
| Load testing | Existing phase load harnesses exist, but 10K follower mass-copy load was not run in this environment. | DEFER | Must run before public copy trading launch. |

## Unsafe Patterns Removed

- The previous copy service no longer submits malformed futures orders.
- Follower copied trades now go through the existing `FuturesOrderService`, `FuturesRiskEngineService`, `ReservationService`, and canonical futures account projection.
- Duplicate lead trade events are idempotent by lead trader, product, and lead trade ID.
- Duplicate copy fanout is idempotent by copy relationship and lead trade event.
- Profit-share is recorded as an accrual above the follower relationship high-water mark, not as a frontend-calculated value.

## Deferred Items

- Spot copy execution through the spot OMS.
- Copy-specific private WebSocket replay stream.
- Automated surveillance scoring and related-account graph integration.
- 1K/10K follower fanout and mass-close load testing.
- Production compliance approval, lead-trader operations procedures, and customer launch policy.
