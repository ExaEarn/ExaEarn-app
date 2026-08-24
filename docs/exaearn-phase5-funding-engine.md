# ExaEarn Phase 5 Funding Engine

`FuturesFundingService` provides:

- funding rate calculation
- funding rate records
- idempotent per-position funding payment records
- canonical ledger settlement through `SettlementService::futuresFundingPayment`

Idempotency key:

```text
futures-funding:{position_id}:{funding_timestamp}
```

Duplicate scheduler runs return the original funding payment.
