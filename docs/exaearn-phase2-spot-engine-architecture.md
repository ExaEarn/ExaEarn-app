# ExaEarn Phase 2 Spot Engine Architecture

Date: 2026-08-17

## Architecture

Phase 2 introduces a production-oriented Spot trading core:

```text
TradeController
  -> TradeService compatibility facade
  -> OrderManagementService
  -> PreTradeValidationService
  -> ReservationService
  -> SpotSequencer
  -> SpotMatchingEngine
  -> ExecutionJournalService
  -> SettlementService
  -> LedgerService
  -> OrderBookSnapshotService
```

`TradeService` remains for route compatibility and legacy rollback. When:

```text
TRADING_ENGINE_MODE=new
```

non-conditional Spot orders are routed to `OrderManagementService`.

## Responsibilities

### OMS

`OrderManagementService` owns:

- canonical order creation
- client order ID idempotency
- reservation coordination
- sequence assignment
- engine command execution
- order state updates
- trade persistence
- settlement outbox persistence
- settlement delivery
- snapshot/update publication
- cancel requests

### Instrument Service

`InstrumentService` owns:

- symbol normalization
- market lookup
- market trading status
- tick size validation
- lot size validation
- min/max quantity
- min/max notional
- quote amount calculation

### Pre-Trade Validation

`PreTradeValidationService` owns:

- side validation
- order type validation
- time-in-force validation
- post-only compatibility
- market eligibility
- precision checks
- duplicate client order ID detection

### Sequencer

`SpotSequencer` assigns monotonically increasing per-market sequence numbers in `spot_engine_sequences`.

### Matching Core

`SpotMatchingEngine` is pure matching logic. It does not write:

- balances
- reservations
- ledger entries
- wallet rows

It returns deterministic fills and terminal/resting decisions.

### Execution Journal

`spot_execution_events` stores append-only engine events:

- `ORDER_ACCEPTED`
- `ORDER_OPENED`
- `ORDER_PARTIALLY_FILLED`
- `ORDER_FILLED`
- `ORDER_CANCELLED`
- `ORDER_REJECTED`
- `TRADE_EXECUTED`

### Settlement

Every execution is settled through Phase 1:

```text
SettlementService::spotTrade(...)
```

Settlement references are unique:

```text
spot-fill:{execution_id}
```

## Supported Order Semantics

- Limit
- Market with liquidity requirement and price protection hook
- GTC
- IOC
- FOK
- Post-only
- Partial fill
- Cancel
- Self-trade prevention default: `CANCEL_NEWEST`

## Persistent Tables

Phase 2 adds:

- `spot_engine_sequences`
- `spot_execution_events`
- `spot_order_book_snapshots`
- `spot_settlement_outbox`

Phase 2 extends:

- `markets`
- `orders`
- `trades`

## Compatibility

The existing `/api/trade/*` routes remain.

The old `order_books` table remains a materialized read model for frontend/API compatibility, not the authoritative matching structure.

