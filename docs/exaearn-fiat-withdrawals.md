# ExaEarn Fiat Withdrawals

Withdrawal flow:

```text
Verified beneficiary
  -> quote
  -> limits and risk
  -> ReservationService hold
  -> provider transfer submission
  -> provider status / webhook / admin confirmation
  -> SettlementService::fiatWithdrawalSettle
```

Failure flow:

```text
Provider failure
  -> release reservation
  -> mark failed
  -> no user debit
```

Duplicate payout protection:

- Unique `user_id + idempotency_key` on `phase10_fiat_withdrawals`.
- Unique `provider + idempotency_key` on `provider_transfers`.
- Settlement uses unique ledger references.

Large withdrawals can enter `UNDER_REVIEW` before reservation/submission.
