# ExaEarn Developer P1 Wave 1 External Interfaces Audit

Date: 2026-08-30

## Scope and before state

| Finding | Before state | Evidence |
|---|---|---|
| External authenticated Developer WebSocket gateway | MISSING / DISCONNECTED | Durable sessions and replay existed, but no network transport consumed `devws_` sessions |
| Webhook atomic claiming | UNSAFE | `deliverDue()` selected due rows without transactional claim ownership |
| Webhook environment isolation | PARTIAL | Endpoint column existed but registration did not assign it; deliveries had no project/environment identity |
| Central webhook schemas | MISSING | Caller-provided arrays were wrapped and emitted without event-specific allowlists |

P0 controls were rechecked and preserved. This wave did not address governance, admin, CI, deployment, backup or dependency P1 findings.

## Reused architecture

- Signed REST API creates short-lived `devws_` sessions.
- Laravel remains authoritative for credentials, scopes, project/environment state, Production capabilities, institutional compliance and replay.
- `developer_realtime_events` remains the durable private event journal.
- Existing Redis provides best-effort live fanout; durable replay remains recovery authority.
- Existing Node HTTP server and `ws` package host the external transport.
- Existing webhook signing, retries, dead letter and stable event IDs remain intact.

## WebSocket transport design

Endpoints:

```text
/ws/developer/sandbox
/ws/developer/production
```

The client first obtains a session through signed REST, connects to the matching environment endpoint, and sends:

```json
{"op":"authenticate","session_id":"devws_..."}
```

Node calls service-authenticated Laravel authority endpoints. Laravel revalidates the session, API key, project, workspace/organization, environment, topic scopes, Production capabilities and compliance. No API secret is sent over WebSocket or placed in a URL.

Properties:

- distinct sandbox/production paths;
- topic-specific scope and capability validation;
- five-second authentication timeout;
- configurable per-session connections, subscriptions, commands and message sizes;
- ping/pong heartbeat and parent-state revalidation;
- immediate closure on the next authority check after credential/capability revocation;
- bounded `bufferedAmount` with slow-consumer disconnect;
- durable per-project/per-environment/per-stream replay;
- stable event ID, stream sequence, event type and timestamp;
- explicit `reconcile_required` response when a gap cannot be represented as contiguous replay;
- Redis live publication filtered by project, environment and stream;
- service-restart close code for reconnect/replay.

Ordering is per project and stream, not global. Delivery/replay is at least once; clients must deduplicate by event ID.

## Webhook claiming design

Due rows are selected inside a database transaction and atomically moved to `DELIVERING` with a UUID claim token, claim timestamp and expiry. PostgreSQL uses `FOR UPDATE SKIP LOCKED`; SQLite tests use transactional row locking compatibility. Retry selection excludes active claims. Expired leases return to retry state, allowing worker-crash recovery.

Network delivery remains at least once. Stable event IDs allow consumer deduplication. PostgreSQL multi-process execution remains deployment verification work.

## Environment and tenant isolation

- Endpoint identity is project plus explicit sandbox/production environment.
- Registration validates the selected environment against canonical active project environments; omitted legacy input defaults to sandbox.
- Legacy ambiguous endpoint migration never guesses production; invalid/ambiguous rows become sandbox/disabled.
- Delivery rows persist project and environment.
- Enqueue selects only endpoints matching both project and event environment.
- Delivery rechecks endpoint/delivery project and environment before sending.
- Replay retains original endpoint, event ID, project and environment.
- Production external delivery remains disabled by default pending egress verification.

## Event schema architecture

`DeveloperWebhookEventRegistry` is the canonical event list and serializer policy. Each supported event has an explicit field allowlist. Unknown event types fail. Values are limited to safe scalar fields, sensitive names are denied as defense in depth, payload size is bounded at 64 KiB, and the envelope includes version, stable event ID, project UUID, environment and creation time.

The config/registry drift assertion verifies documented supported event names remain aligned. Arbitrary model serialization is not used.

## Security and network evidence

Laravel focused external-interface/P0/Phase14 tests:

```text
33 passed / 0 failed / 1857 assertions
```

Combined Developer/security/compliance regression:

```text
67 passed / 0 failed / 1998 assertions
```

Node real-network gateway tests:

```text
3 passed / 0 failed
```

Covered authenticated network connection, live event, replay, cross-environment denial, already-connected credential revocation, malformed JSON and unauthorized topic denial.

Load evidence:

```text
100 real sockets: 100 authenticated / 0 failed
Duration: 193 ms
RSS: 65 MB

1,000 burst attempt 1: 232 authenticated / 768 failed
1,000 paced attempt 2: 360 authenticated / 640 failed
```

The 1K local Windows connection gate did not pass. Result: **LOAD HARNESS READY / PRODUCTION CAPACITY VERIFICATION REQUIRED**. No exchange-scale claim is made.

## Deployment verification requirements

- Route both Developer WS paths through production ingress with TLS and upgrade-header controls.
- Protect internal Laravel authority endpoints and rotate the shared service credential.
- Verify Redis HA/failover, event lag and reconnect behavior.
- Execute 1K+ tests on production-like Linux ingress with file descriptor, backlog and ephemeral-port tuning.
- Verify PostgreSQL concurrent `SKIP LOCKED` behavior with multiple real workers and crash injection.
- Verify slow-consumer closure under real ingress/proxy buffering.
- Add telemetry exporter/dashboards for the gateway statistics.
- Keep production webhook egress disabled until P0 egress verification completes.

## Finding disposition

| Finding | Result |
|---|---|
| P1-1 gateway absence | CODE-CLOSED / DEPLOYMENT VERIFICATION REQUIRED |
| P1-2 atomic webhook claim | CODE-CLOSED / POSTGRES DEPLOYMENT VERIFICATION REQUIRED |
| P1-3 environment isolation | CLOSED |
| P1-4 central event policy | CLOSED |

