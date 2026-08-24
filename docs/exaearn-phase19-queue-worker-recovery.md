# ExaEarn Phase 19 Queue and Worker Recovery

## Queue Reliability

`SreQueueReliabilityService` classifies queues as financial-critical, risk-critical, notification or operational. It tracks depth, oldest job age, failed jobs and health status.

## Worker Supervision

`SreWorkerSupervisorService` records worker heartbeats and marks stale workers as `DEAD`.

## Recovery Rules

Workers must use existing idempotency keys, outbox records and canonical settlement. A restarted worker may retry a durable job, but it must not duplicate deposits, withdrawals, orders, fills, settlements or ledger entries.

