# ExaEarn Phase 12 Copy Fanout, Load And Recovery

Phase 12 supports two fanout paths:

- `fanoutLeadExecution`: synchronous processing for tests and controlled internal operations.
- `queueFanoutLeadExecution`: durable follower-decision job dispatch for high-volume production paths.

Follower decisions are idempotent through the database uniqueness constraint:

```text
copy_relationship_id + lead_trade_event_id
```

Risk-reducing events such as `close`, `partial_close`, and `reduce` are assigned high priority and dispatched to `copy-high`. Opening/increase events go to `copy-normal`.

Load run records are persisted in `copy_load_runs` with:

- followers
- successful/skipped/failed decisions
- duplicate decisions
- submitted orders
- financial invariant failures
- fanout duration
- p50/p95/p99 decision latency

Restart recovery relies on:

- durable `copy_lead_trade_events`
- durable `copy_orders`
- idempotent copy-order uniqueness
- durable `copy_realtime_events`
- replay by user stream sequence

If a worker crashes, replaying the same lead event cannot duplicate a follower economic action.
