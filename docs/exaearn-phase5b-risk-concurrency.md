# ExaEarn Phase 5B Risk and Concurrency

Phase 5B uses database row locks around:

- liquidation position ownership
- ADL event execution
- funding settlement idempotency
- reservation-backed order placement
- insurance fund ledger settlement

Duplicate liquidation/ADL processing is guarded by durable state and idempotent ledger references.

Further staging should add high-volume PostgreSQL worker contention tests before live production cutover.
