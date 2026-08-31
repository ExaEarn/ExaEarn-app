# Realtime and REST Capacity Evidence

Local pre-freeze evidence:

```text
Node realtime tests: 8 passed
In-memory-authority WebSocket harness: 1000/1000 authenticated
Duration: 2.144 seconds
RSS: 84 MB
```

This harness does not use Laravel authority, Redis, PostgreSQL, Kubernetes NetworkPolicy, subscriptions under production load, financial event fanout, replay under load, revocation under load, gap recovery, or slow consumers.

```text
Production-equivalent WebSocket: NOT RUN
100/500/1000 deployed tiers: NOT RUN
WS p50/p95/p99: NOT MEASURED
REST capacity/burst: NOT RUN
REST p50/p95/p99/RPS: NOT MEASURED
Redis/database pressure: NOT MEASURED
```

Result: **FAIL**.

