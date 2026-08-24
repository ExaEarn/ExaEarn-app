# ExaEarn Phase 12 Copy Private Realtime

Copy trading now records durable private events in `copy_realtime_events`.

Each event has:

- `user_id`
- `stream`
- `sequence`
- `event_type`
- `payload`
- `published_at`

Sequences are monotonic by `user_id + stream`. Redis publish is best-effort fanout; database replay remains the recovery source.

Replay endpoint:

```text
GET /api/v1/copy-trading/realtime/replay?after_sequence=123&stream=copy
```

Client contract:

1. Store the latest received sequence.
2. If a sequence gap is detected, call replay with `after_sequence=last_contiguous_sequence`.
3. Apply replayed events in sequence order.
4. Do not trigger financial actions from realtime events; OMS, ledger, and copy order records are authoritative.
