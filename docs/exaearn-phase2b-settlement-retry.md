# ExaEarn Phase 2B Settlement Retry

Phase 2B adds a settlement outbox retry worker for spot executions.

## Implemented Components

- Existing model: `App\Models\SpotSettlementOutbox`
- Service: `App\Services\Spot\SettlementOutboxService`
- Command: `php artisan spot:settlement-outbox {--limit=100}`

## Statuses

The worker supports:

- `pending`
- `retryable`
- `failed_retryable`
- `processing`
- `settled`
- `failed_manual_review`

## Settlement Contract

Each outbox row contains a deterministic `reference`. The retry worker calls the canonical `SettlementService::spotTrade(...)` with that reference. The ledger idempotency constraint prevents duplicate ledger posting if the worker retries.

## Failure Handling

Transient failures increment attempts and move the row back to a retryable state until `settlement_max_attempts` is reached. Exhausted rows move to `failed_manual_review`.

## Current Integration Note

The OMS still attempts synchronous settlement after journal creation for immediate consistency in the current Laravel architecture. The outbox worker is now available for recovery of pending/retryable rows and proves logical exactly-once settlement through the canonical ledger reference.

## Test Coverage

`Tests\Feature\Phase2BAuthorityTest::test_settlement_outbox_retry_is_logically_exactly_once` verifies that repeated processing of one outbox row creates only one ledger transaction.

