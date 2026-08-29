# ExaPay Settlement

Merchant payable is not a wallet. It is calculated from captured payment intents and settlement batches.

Settlement batch fields:

- gross amount
- refund amount
- fee amount
- net amount
- status
- ledger reference
- metadata

States:

```text
PENDING
READY
HELD
APPROVAL_REQUIRED
PROCESSING
PROVIDER_PENDING
PROVIDER_UNKNOWN
COMPLETED
FAILED
REVERSED
```

Production bank/processor payout submission remains disabled until provider credentials, destination security and operational procedures are completed.
