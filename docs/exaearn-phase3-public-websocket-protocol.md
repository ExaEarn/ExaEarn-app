# ExaEarn Phase 3 Public Market Stream Protocol

Phase 3 formalizes the public stream contract while preserving the current Laravel/SSE-compatible infrastructure and upgrading the existing Node `/ws/markets` hub.

## Topic Names

```text
market.{SYMBOL}.ticker
market.{SYMBOL}.book
market.{SYMBOL}.trade
market.{SYMBOL}.kline.{INTERVAL}
```

Examples:

```text
market.BTCUSDT.ticker
market.BTCUSDT.book
market.ETHUSDT.trade
market.SOLUSDT.kline.1m
```

## WebSocket Endpoint

```text
/ws/markets
```

Subscribe:

```json
{
  "op": "subscribe",
  "topics": [
    "market.BTCUSDT.ticker",
    "market.BTCUSDT.book"
  ]
}
```

Unsubscribe:

```json
{
  "op": "unsubscribe",
  "topics": ["market.BTCUSDT.book"]
}
```

Heartbeat:

```json
{"op":"ping"}
```

Server response:

```json
{"op":"pong","timestamp":"..."}
```

## Snapshot Endpoint

`GET /api/v1/market/stream/snapshot`

Input:

```json
{
  "topics": ["market.BTCUSDT.ticker", "market.BTCUSDT.book"],
  "after_sequence": 0
}
```

Output:

```json
{
  "op": "snapshot",
  "topics": {
    "market.BTCUSDT.ticker": {},
    "market.BTCUSDT.book": {
      "snapshot": {},
      "deltas": []
    }
  },
  "timestamp": "..."
}
```

## Delta Ordering

Clients must apply a snapshot first, then deltas with contiguous sequence numbers. Multiple events may share one sequence because a single engine sequence can publish `BOOK_DELTA`, `BEST_BID_ASK`, and `TRADE`.

## Gap Recovery

If a client expects sequence `N + 1` and receives a later sequence, it must discard local state and fetch a new snapshot.

## Operational Requirements

The market hub preserves:

- bounded subscription limits
- heartbeat/ping-pong
- slow-client backpressure
- disconnect/resync on overflow
- public/private stream separation
