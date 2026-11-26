# ExaEarn Listing Durable Launch Dispatcher Remediation

Date: 2026-09-01

## Previous architecture and failure mode

Listing schedules and per-capability launch events were persisted, but due events were discovered and executed synchronously by an Admin HTTP endpoint. No scheduler command or dedicated queue job invoked them. One transaction locked every due event while activation and content side effects ran. There was no durable claim/lease, bounded retry state, failed-operation evidence, or restart recovery. A scheduler outage did not recover automatically because no scheduler was registered.

## Durable state and database authority

The existing `listing_launch_events` table is retained as the canonical launch operation and transactional outbox. Legacy event `status` remains compatible (`PENDING`, `EXECUTED`, `BLOCKED`), while `operation_status` records the durable lifecycle:

`SCHEDULED -> DISPATCH_PENDING -> DISPATCHED -> EXECUTING -> SUCCEEDED`

Exceptional paths use `RETRY_PENDING`, `BLOCKED`, `FAILED`, and `CANCELLED`. Each operation stores its stable event UUID/idempotency key, correlation ID, configuration fingerprint, launch scope, attempts, claim token, lease, dispatch/start/completion times, heartbeat, and structured failure evidence. Redis is delivery infrastructure only.

## Scheduler, claiming, and queue execution

`listing:launches:dispatch` runs every minute with Laravel `withoutOverlapping()` and `onOneServer()`. `ListingLaunchDispatcherService` additionally locks due database rows and assigns leases/tokens, protecting deployments with multiple scheduler instances. It dispatches `ExecuteListingLaunchEvent` to the dedicated `listing-launch` queue. Duplicate handoff remains safe because execution requires the current claim token and terminal operations are no-ops.

The database event itself provides the transactional-outbox equivalent: scheduling and event creation commit together. A failed queue handoff leaves the operation in `RETRY_PENDING`; it is never marked delivered first. Stale dispatch/execution leases are reclaimed. Overdue events remain discoverable, while events beyond the configurable maximum lateness window fail closed as `LAUNCH_WINDOW_EXPIRED`.

## Execution-time controls

Immediately before side effects, the worker reloads current application, organization, asset, network, market, and schedule state. It verifies:

- application approval;
- approved KYB when production policy requires it;
- current clean KYB and Listing documents;
- exact integration-certification configuration fingerprint;
- contract validation, liquidity, market configuration, and latest required certification results;
- existing deposit and withdrawal readiness/dual approval through their canonical services.

Material configuration changes block with `CONFIGURATION_FINGERPRINT_MISMATCH`. Trading, deposit, and withdrawal are separate events/scopes. A trading event cannot enable deposits or withdrawals. Postconditions are represented by the canonical event outcome and only `EXECUTED` operations become `SUCCEEDED`.

## Retry, crash recovery, and dead letter

The queue job has three attempts, a 120-second timeout, and bounded backoff of 30, 120, and 600 seconds. Exceptions persist either `RETRY_PENDING` or terminal `FAILED`; Laravel failed-job storage remains the queue dead letter record and the Listing operation retains product-level evidence. Worker death leaves an expiring execution lease, allowing later recovery without marking success. Permanent policy failures are `BLOCKED` and are not retried automatically.

## Cancellation and operations visibility

The Admin Listing Center exposes operation history and metrics for scheduled, overdue, executing, stale, succeeded, blocked, retry-pending, and failed operations. Authorized cancellation locks the row, records audit evidence, and changes the operation to `CANCELLED`; an already queued stale job then becomes an idempotent no-op. The legacy launch endpoint now requests durable dispatch and never activates a market in the HTTP request.

Rescheduling and retry after terminal remediation must use a separately authorized workflow before production enablement; historical operation evidence must not be overwritten. Emergency controls that suspend an application cause execution-time gates/status checks to fail closed.

## Atomicity and partial failure

Database state changes inside each canonical activation step use existing transactions. External queue publication and notification/content delivery are not claimed to be atomic with database changes. Every step is idempotent and retries inspect current canonical state. Notification/content failure can be retried without reversing a correctly completed financial activation; terminal failure remains visible.

## Production runtime requirements

Software completion does not deploy operations. Production requires:

- one supervised Laravel scheduler (`schedule:work` or cron `schedule:run`);
- supervised workers consuming `listing-launch` with the configured timeout/tries;
- durable PostgreSQL and queue/Redis infrastructure;
- Laravel failed-job storage and retention;
- metrics scraping and alerts for overdue, blocked, stale, retry-exhausted, and failed operations;
- process restart through Kubernetes, systemd, or Supervisor;
- an operator/on-call runbook and tested escalation path.

Status: **OPERATIONAL SETUP REQUIRED** until those components are deployed and observed.

## Runbook

1. Normal launch: inspect readiness, approve schedule, confirm operation `SCHEDULED`, then monitor dispatch and postconditions.
2. Blocked launch: inspect `failure_code`, remediate the exact gate, obtain required authorization, create a fresh fingerprint-bound schedule/operation, and never edit historical evidence.
3. Worker outage: restore worker supervision; stale leases are reclaimed automatically.
4. Scheduler outage: restore scheduler; due operations inside the lateness window are recovered.
5. Excessively late launch: leave blocked and require manual review/rescheduling.
6. Cancellation: cancel before execution with a recorded reason; verify stale jobs no-op.
7. Partial/failed launch: halt further capability activation, inspect canonical asset/market/capability state, reconcile, and use controlled recovery.
8. Emergency stop: suspend/pause through existing emergency controls; execution revalidation then fails closed.

## Outbox decision

No second generic outbox table was introduced. The existing durable, uniquely keyed launch-event record already carries the outbox identity, scheduling state, claims, retry evidence, and terminal result. This avoids two competing sources of truth while closing the database-commit/queue-publish gap.
