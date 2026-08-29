# ExaEarn Crowdfunding Refunds

Refunds are batch-based and ledger-backed.

## Eligible Campaign States

- `FAILED`
- `CANCELLED`
- `REFUNDING`
- `SUSPENDED`
- `REFUNDED` for idempotent replay of existing batches

## Safety

Each pledge refund has a stable ledger reference. Replaying the same refund reason returns the existing processing/completed batch when present.

