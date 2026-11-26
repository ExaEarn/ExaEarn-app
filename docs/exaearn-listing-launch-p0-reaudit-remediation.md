# ExaEarn Listing Launch P0 Re-Audit Remediation

Date: 2026-09-01
Scope: the three launch-orchestration P0 defects identified by the independent Listing re-audit

## 1. P0-1 Root Cause

The first durable-launch migration added `operation_status` with a `SCHEDULED` default to a table that already contained legacy launch events. It did not map the existing `status` column. Consequently, historical `EXECUTED`, `BLOCKED`, `FAILED`, `CANCELLED`, and ambiguous `PENDING` rows could appear executable to the new dispatcher.

## 2. Historical State Classification

| Legacy evidence | Classification | Durable operation state |
|---|---|---|
| `status=EXECUTED` | TERMINAL_SUCCESS | `SUCCEEDED` |
| `status=BLOCKED` | TERMINAL_FAILURE | `BLOCKED` |
| `status=FAILED` | TERMINAL_FAILURE | `FAILED` |
| `status=CANCELLED` | CANCELLED | `CANCELLED` |
| `status=PENDING` with no durable fingerprint, correlation ID, or launch scope | AMBIGUOUS_LEGACY | `LEGACY_UNVERIFIED` |
| Any other pre-durable status without complete durable scheduling evidence | AMBIGUOUS_LEGACY | `LEGACY_UNVERIFIED` |
| New `PENDING` event with fingerprint, correlation ID, launch scope and explicit operation state | GENUINELY_SCHEDULED | Preserved as `SCHEDULED` |

No legacy pending record is inferred to be safe merely from its due date. Ambiguous records require future governed review and cannot be selected by the dispatcher.

## 3. Migration Correction

The original migration now classifies every row that exists before durable columns are introduced. Provable terminal outcomes retain terminal semantics. Everything else is quarantined as `LEGACY_UNVERIFIED` with an explicit failure code and reason.

This protects fresh installations and direct upgrades that have not yet run the unsafe migration.

## 4. Forward Corrective Migration

`2026_09_10_000001_correct_listing_launch_operation_states.php` repairs environments where the unsafe migration may already have run. It:

- maps terminal legacy `status` values regardless of the unsafe current operation state;
- quarantines incomplete durable rows even if they already reached `DISPATCH_PENDING`, `DISPATCHED`, or `EXECUTING`;
- clears obsolete claims and leases;
- preserves genuinely scheduled rows carrying all durable evidence;
- can rerun its repair method without changing the resulting classification;
- adds a separate `dispatch_attempt_count` without conflating queue transport attempts with financial/capability execution attempts.

Production migration procedure must stop Listing dispatchers/workers before applying the corrective migration, run the migration, inspect `LEGACY_UNVERIFIED`, and only then restart workers. The migration never fabricates success or authorizes retry.

## 5. P0-2 Root Cause

The dispatcher changed accepted queue handoffs to `DISPATCHED`, but selected only `SCHEDULED`, `RETRY_PENDING`, `DISPATCH_PENDING`, and `EXECUTING`. A message lost after queue acceptance was therefore never rediscovered after lease expiry.

## 6. Dispatch Lease

The existing bounded `lease_expires_at` remains the ownership lease. Dispatch now increments `dispatch_attempt_count`, issues a new random claim token for every transport attempt, and records `dispatched_at`. `attempt_count` remains the execution-attempt counter.

The dispatcher explicitly selects only:

- `SCHEDULED`
- `RETRY_PENDING`
- `DISPATCH_PENDING` with no live lease
- `DISPATCHED` with no live lease

Terminal, cancelled, legacy-unverified, partially failed, and actively executing events are excluded.

## 7. Lost-Message Recovery

An expired `DISPATCHED` lease is automatically claimed again with a new token and republished. An old message carrying the prior token cannot claim the operation. The database record therefore remains authoritative when Redis loses a message.

Expired `EXECUTING` work is not automatically replayed because an old worker may already have crossed the side-effect boundary. It becomes `PARTIALLY_FAILED` with `EXECUTION_LEASE_EXPIRED`, requiring reconciliation. Governed terminal retry/resume is intentionally left to the existing P1 remediation list.

## 8. Redis and Queue Failure Semantics

- Publish throws before delivery: operation becomes `RETRY_PENDING`, retains error evidence, and is automatically rediscovered.
- Publish succeeds: operation becomes `DISPATCHED` only through a token/state-conditional update.
- Publish is ambiguous: the operation may be redispatched, but the new token fences the original message.
- Message disappears or Redis restarts: the expired dispatch lease makes the DB operation eligible again.
- Obsolete job failure callback: token and `EXECUTING` state must both match, preventing an old job from poisoning a replacement claim.
- Duplicate delivery: only the job holding the current token can transition from a worker-claimable state into `EXECUTING`.

The delivery contract is at least once. Exactly-once queue delivery is neither assumed nor required.

## 9. P0-3 Root Cause

Cancellation previously rejected only selected terminal states and allowed `EXECUTING`. Worker execution happened outside the cancellation transaction, so cancellation could persist `CANCELLED` while the worker continued activating markets or capabilities.

## 10. Cancellation Semantics

Cancellation is allowed only from:

- `SCHEDULED`
- `RETRY_PENDING`
- `DISPATCH_PENDING`
- `DISPATCHED`

A successful cancellation is terminal, clears the lease, and causes every queued job to exit because `CANCELLED` is not worker claimable.

Cancellation from `EXECUTING` returns `EXECUTION_IN_PROGRESS: launch operation is too late to cancel`. Terminal and quarantined operations also reject cancellation. Existing emergency capability controls remain the intervention path after execution begins.

## 11. Execution Commit Boundary

The atomic row-locked transition from `DISPATCH_PENDING` or `DISPATCHED` to `EXECUTING` is the non-cancellable execution boundary. The transaction verifies:

- the operation is nonterminal;
- the job token matches the authoritative claim;
- the current state is worker claimable;
- no other worker has already crossed the boundary.

If cancellation locks first, the job sees `CANCELLED` and performs no side effects. If the worker locks first and commits `EXECUTING`, cancellation reports a conflict and cannot falsely report success.

## 12. State Transition Matrix

| Current state | Permitted next states |
|---|---|
| `SCHEDULED` | `DISPATCH_PENDING`, `CANCELLED`, `BLOCKED` |
| `RETRY_PENDING` | `DISPATCH_PENDING`, `CANCELLED`, `BLOCKED` |
| `DISPATCH_PENDING` | `DISPATCHED`, `RETRY_PENDING`, `EXECUTING`, `CANCELLED`, `BLOCKED` |
| `DISPATCHED` | `DISPATCH_PENDING` after lease expiry, `EXECUTING`, `CANCELLED`, `BLOCKED` |
| `EXECUTING` | `SUCCEEDED`, `BLOCKED`, `RETRY_PENDING`, `FAILED`, `PARTIALLY_FAILED` |
| `SUCCEEDED` | none |
| `FAILED` | none in this P0 phase |
| `BLOCKED` | none in this P0 phase |
| `CANCELLED` | none |
| `PARTIALLY_FAILED` | none in this P0 phase |
| `LEGACY_UNVERIFIED` | none |

The model exposes canonical state sets used by dispatcher, worker, cancellation and failed-job paths. Server-side queries enforce the relevant source state and claim token.

## 13. Concurrency Protections

- Two dispatchers: `lockForUpdate` serializes claims.
- Duplicate workers: state and claim-token fencing allows one execution claim.
- Dispatcher versus cancellation: cancellation can win before execution; a queued job then no-ops.
- Worker versus cancellation: the row lock determines the winner at the explicit execution boundary.
- Old worker versus redispatch: new transport attempt receives a new token; old job cannot claim or mark failure.
- Stale executing worker: no automatic redispatch; operation fails closed for reconciliation.
- Scheduler recovery versus cancellation: cancelled state is outside dispatcher selection.

No process-local lock is part of correctness.

## 14. Failure Recovery

Database state survives Redis/queue loss. Dispatch failures and stale dispatches recover automatically. Stale execution is surfaced rather than replayed. Configuration fingerprints, current Listing/KYB/document/certification/readiness checks, capability isolation and postcondition verification remain enforced.

The existing P1 work for post-side-effect crash convergence and governed terminal repair remains separate and was not disguised as P0 completion.

## 15. Tests

Focused launch tests cover:

- fresh migration through `RefreshDatabase`;
- already-migrated terminal and ambiguous rows;
- correction idempotence;
- terminal rows excluded from dispatch;
- legitimate durable schedule preservation;
- duplicate dispatchers;
- expired `DISPATCHED` redispatch and fresh fencing token;
- synchronous queue publish failure;
- ambiguous queue acceptance plus publisher timeout;
- original and replacement job delivery;
- stale execution fail-closed handling;
- cancellation before dispatch;
- cancellation after dispatch but before worker;
- execution boundary conflict;
- obsolete failed-job callback;
- changed fingerprint blocking before side effects;
- retry exhaustion evidence.

Validation results:

- Launch/P0/MarketData focused: 45 passed, 401 assertions.
- Final complete backend: 625 passed, 0 failed, 1 skipped, 5,086 assertions.
- PHP syntax: PASS.
- Listing typecheck: PASS.
- Listing production build: PASS.
- Listing lint: NOT_CONFIGURED.

## 16. Operational Implications

Before deployment, operators must pause Listing scheduler/workers, apply the corrective migration, review quarantined rows, and restart only after migration verification. Scheduler and queue runtime deployment remain unverified external/operational work. `LEGACY_UNVERIFIED` and stale `PARTIALLY_FAILED` rows must not be edited directly into executable states.

This remediation closes the three launch P0 defects at the software layer. It does not close the previously reported P1 work, connect external providers, authorize Production capabilities, or make the complete Listing product production ready.
