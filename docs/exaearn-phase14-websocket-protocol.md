# ExaEarn Phase 14 WebSocket Protocol

## Status

Phase 14 provides the backend contract for external developer WebSocket sessions and replay. The transport fanout must reuse the existing ExaEarn realtime infrastructure; the developer gateway must not create financial effects.

## Public Topics

```text
market.{symbol}.ticker
market.{symbol}.book
market.{symbol}.trade
market.{symbol}.kline.{interval}
```

Example subscribe request:

```json
{
  "op": "subscribe",
  "topics": ["market.BTC-USDT.ticker", "market.BTC-USDT.trade"]
}
```

## Private Topics

Private streams require the same developer API key identity and signed session bootstrap.

Bootstrap endpoint:

```text
POST /api/developer/v1/realtime/session
```

Replay endpoint:

```text
GET /api/developer/v1/realtime/replay?stream=account.balance&after_sequence=100
```

Planned topics:

```text
account.balance
spot.order
spot.fill
wallet.transaction
webhook.delivery
```

## Ordering

Streams that represent authoritative market or account changes must include:

```text
sequence
event_type
payload
created_at
```

Clients must resync through REST/replay when a gap is detected.

## Gap Recovery

```text
last_sequence = 100
received = 103
pause local application
call replay after_sequence=100
apply 101, 102, 103 in order
resume
```

Do not invent missing events.

## Safety Rules

- WebSocket replay must never create financial effects.
- Slow clients must not block market-data producers.
- Public streams need subscription limits, heartbeat and malformed message protection.
- Private streams must not expose secrets or unauthorized account state.
- Slow consumers must be disconnected after bounded queue overflow and must reconnect/replay.
# Final Gateway Validation

Phase 14 validates durable developer realtime sequencing and replay through `DeveloperRealtimeService`.

Local verification published 1,000 ordered private events on `account.balance` and replayed the tail from sequence 995 without gaps. A genuine 10,000 external network-socket benchmark requires a deployed WebSocket gateway and is marked environment blocked in the completion report rather than falsely passed.
