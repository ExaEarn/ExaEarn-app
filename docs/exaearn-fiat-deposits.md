# ExaEarn Fiat Deposits

Deposit flow:

```text
Provider webhook
  -> signature verification
  -> normalized webhook event
  -> idempotent provider_webhook_events insert
  -> fiat_deposits detection
  -> verification
  -> SettlementService::fiatDepositCredit
  -> ledger credit to user funding account
```

Exactly-once protections:

- Unique `provider + event_id` on webhook events.
- Unique `provider + provider_transaction_id` on deposits.
- Unique ledger reference `fiat-deposit:{provider}:{provider_transaction_id}`.

Unmatched deposits are `MANUAL_REVIEW`. They are not auto-credited.
