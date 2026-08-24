# ExaEarn Matching Recovery

Date: 2026-08-17

## Durable State

Phase 2 persists:

- accepted/open/cancelled/filled orders in `orders`
- public trades in `trades`
- engine events in `spot_execution_events`
- snapshots in `spot_order_book_snapshots`
- settlement delivery state in `spot_settlement_outbox`

Redis/WebSocket delivery is not authoritative.

## Snapshot Model

`OrderBookSnapshotService` writes snapshots containing:

- market
- last sequence
- bid levels
- ask levels
- open order references
- checksum

The existing `order_books` table is updated as a compatibility read model.

## Replay Model

Current Phase 2 replay verification compares the latest persisted snapshot checksum against current open order state.

The next hardening step should implement a full event-only replay runner that:

```text
latest snapshot
  -> journal events after snapshot sequence
  -> rebuild in-memory book
  -> compare checksum and open order state
```

## Crash Scenarios

Covered by current persistence design:

- command accepted before realtime publish
- execution journal exists before WebSocket publish
- settlement outbox persists settlement intent
- duplicate settlement reference prevents double ledger posting
- duplicate client order ID prevents duplicate order creation

Still requiring hardening before production authority:

- automated settlement retry worker
- full replay from journal into an empty process
- leader lease/failover for multiple engine workers
- gap-detecting public/private order-book streams
- operational snapshot schedule

## Settlement Failure

If `SettlementService::spotTrade()` fails after trade creation:

- trade is marked `failed_retryable`
- `spot_settlement_outbox` row is marked `failed_retryable`
- error is recorded
- transaction rolls back if the exception occurs inside the order-processing transaction

Exactly-once logical settlement remains protected by unique settlement references.

