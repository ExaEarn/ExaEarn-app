# ExaEarn Affiliate Commission Events

Commission records live in `affiliate_commission_events`.

Each event stores:
- product
- event type
- source reference
- gross revenue
- commissionable base
- commission rate
- commission amount
- reward asset
- reward policy decision
- affiliate user
- referred user
- referral relationship
- status
- policy snapshot

Uniqueness:

```text
product + event_type + source_reference + affiliate_user_id
```

This prevents duplicate commission from retries, queue replay or worker restart.

States:

```text
PENDING
HELD
AVAILABLE
PAID
REVERSED
CLAWBACK_PENDING
```

The broader target lifecycle also reserves names for future expansion:

```text
DETECTED
QUALIFIED
CLAWED_BACK
EXPIRED
REJECTED
```
