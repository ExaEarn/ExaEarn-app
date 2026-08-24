# ExaEarn Phase 2 Spot Matching Report

Date: 2026-08-17

## A. Architecture Implemented

Implemented a Phase 2 Spot trading core behind the existing `/api/trade/*` compatibility facade.

Flow:

```text
TradeController
  -> TradeService
  -> OrderManagementService
  -> PreTradeValidationService
  -> ReservationService
  -> SpotSequencer
  -> SpotMatchingEngine
  -> ExecutionJournalService
  -> SettlementService
  -> Canonical Ledger
```

The legacy matcher remains available for rollback.

## B. Runtime Decision

Runtime: Laravel/PHP inside `backend/api-gateway`.

Reason: preserve existing API/ledger/test infrastructure and prove deterministic OMS/sequencer/journal semantics before considering a separate Go/Rust/Node engine.

Document:

```text
docs/exaearn-matching-engine-runtime-decision.md
```

## C. OMS

Implemented:

```text
App\Services\Spot\OrderManagementService
```

Responsibilities:

- creates accepted orders
- supports client order ID idempotency
- reserves funds via Phase 1 `ReservationService`
- assigns per-market sequence
- calls matching core
- updates order states
- creates trades
- creates settlement outbox rows
- settles through `SettlementService`
- writes snapshots
- handles cancellation

Status: READY for sandbox/shadow validation.

## D. Instrument Service

Implemented:

```text
App\Services\Spot\InstrumentService
```

Centralizes:

- symbol normalization
- market lookup/status
- tick size
- quantity step
- min/max quantity
- min/max notional

Status: READY.

## E. Pre-Trade Validation

Implemented:

```text
App\Services\Spot\PreTradeValidationService
```

Validates:

- market active/trading
- side
- type
- time-in-force
- post-only compatibility
- precision
- min/max quantity
- min/max notional
- duplicate client order ID

Status: READY for implemented order types.

## F. Sequencer

Implemented:

```text
App\Services\Spot\SpotSequencer
spot_engine_sequences
```

Sequences are monotonically increasing per market using database row locks.

Status: READY for single Laravel authoritative worker mode.

Remaining production hardening:

- market ownership lease for multi-worker deployments
- explicit failover semantics

## G. Matching Engine

Implemented:

```text
App\Services\Spot\SpotMatchingEngine
```

The matcher is a pure matching core:

- no wallet writes
- no ledger writes
- no reservation writes
- deterministic sort and fill generation

Status: READY for implemented scenarios.

## H. Order Book

The engine builds in-memory book state from active orders and sorts:

- bids by higher price, then lower sequence
- asks by lower price, then lower sequence

`order_books` remains a read-model snapshot for compatibility.

## I. Supported Order Types

Implemented and tested:

- Limit
- Market
- GTC
- IOC
- FOK
- Post-only
- Cancel

Conditional stop-loss/take-profit routes remain on legacy code for now.

## J. Self-Trade Prevention

Default implemented:

```text
CANCEL_NEWEST
```

If incoming order would match same user, incoming order is cancelled and no trade is created.

## K. Execution Journal

Implemented:

```text
spot_execution_events
ExecutionJournalService
```

Events:

- `ORDER_ACCEPTED`
- `ORDER_OPENED`
- `ORDER_PARTIALLY_FILLED`
- `ORDER_FILLED`
- `ORDER_CANCELLED`
- `ORDER_REJECTED`
- `TRADE_EXECUTED`

Status: READY as append-only event journal.

## L. Snapshot/Replay

Implemented:

```text
spot_order_book_snapshots
OrderBookSnapshotService
```

Snapshots include bid levels, ask levels, open orders, last sequence and checksum.

Tested:

- deterministic checksum against current open order state

Remaining production hardening:

- full journal-only replay runner after process restart

## M. Settlement Integration

Implemented:

- trade execution creates settlement reference
- outbox row is persisted
- `SettlementService::spotTrade()` posts ledger entries
- reservations are consumed/released through Phase 1 services
- duplicate settlement references remain ledger-idempotent

Status: PASS in focused tests.

## N. Realtime Integration

Existing `MarketStreamService` is reused for order-book publishing.

Remaining Phase 3 work:

- sequenced public market-data protocol
- gap detection
- replay/resync windows
- private order/execution stream

## O. Legacy Matcher Migration

Feature flag:

```text
TRADING_ENGINE_MODE=legacy
TRADING_ENGINE_MODE=new
```

Default remains:

```text
legacy
```

This prevents accidental production cutover.

## P. Database Changes

Migration:

```text
2026_08_17_000001_create_phase2_spot_engine_tables
```

Adds/extends:

- `markets.tick_size`
- `markets.quantity_step`
- `markets.min_notional`
- `markets.max_notional`
- `markets.trading_status`
- `orders.client_order_id`
- `orders.time_in_force`
- `orders.post_only`
- `orders.sequence`
- `orders.reservation_id`
- `trades.sequence`
- `trades.maker_order_id`
- `trades.taker_order_id`
- `trades.settlement_status`
- `trades.settlement_reference`
- `spot_engine_sequences`
- `spot_execution_events`
- `spot_order_book_snapshots`
- `spot_settlement_outbox`

## Q. Unit Tests

Added:

```text
tests/Feature/Phase2SpotEngineTest.php
```

## R. Concurrency Tests

Phase 1 PostgreSQL reservation concurrency gate still passes:

```text
php artisan financial:phase1-gate
SAFE TO BEGIN PHASE 2: YES
```

Phase 2 sequencer uses row locks but does not yet include a dedicated multi-process order ingress stress test.

## S. Replay Tests

Snapshot checksum replay test passes.

Full event replay from empty process is documented as a remaining hardening item.

## T. End-to-End Tests

Focused Phase 1 + Phase 2 suite:

```text
php artisan test tests/Feature/Phase1FinancialCoreTest.php tests/Feature/SpotFinancialMigrationTest.php tests/Feature/Phase2SpotEngineTest.php
```

Result:

```text
24 passed, 110 assertions
```

## U. Load Results

No production load test was run in this pass.

The current implementation prioritizes correctness and deterministic accounting over throughput. A non-production load harness should be added before sandbox authority.

## V. Remaining Risks

Phase 2 is not yet ready to become fully authoritative in production because:

- default mode remains `legacy`
- no market ownership lease exists for multi-worker split-brain prevention
- no full journal replay runner exists yet
- no settlement retry worker exists for `spot_settlement_outbox`
- no dedicated load/concurrency order-ingress harness exists
- conditional order types still use legacy matcher path
- public/private sequenced WebSocket protocol belongs to Phase 3
- full backend suite still has unrelated non-Phase-2 failures documented in Phase 1 final report

## W. Phase 3 Readiness

Phase 2 now emits/persists sequenced events and snapshots that Phase 3 market-data work can consume.

Do not begin Phase 3 automatically.

## Final Decision

Is ExaEarn Spot matching core ready to become the authoritative internal Spot engine?

```text
NO
```

It is ready for development, sandbox and shadow validation behind the feature flag. The blockers listed in section V should be cleared before production authority.

