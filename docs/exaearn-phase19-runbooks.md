# ExaEarn Phase 19 Runbooks

## Database Outage

Enter safe mode, restore or promote database, run reconciliation, verify security, resume in safe mode.

## Redis Outage

Disable new risk where Redis-backed locks/realtime are required, preserve existing ledger/OMS state, restore Redis, resync clients.

## Queue Backlog

Classify queue, pause non-critical work, protect financial-critical queues, inspect failed jobs, restart workers, verify idempotency.

## Worker Death

Mark stale worker `DEAD`, restart worker, replay durable jobs, verify no duplicate financial effects.

## Market Data Stale

Use bounded reference failover if valid. If sources are stale/divergent, disable new risk.

## RPC Failure

Fail over to healthy same-chain provider. Reject wrong-chain providers. Pause when no valid provider remains.

## Safe Resume

Require maker-checker approval, finance readiness, security readiness and safe-mode resume before normal operations.

