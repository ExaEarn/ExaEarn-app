# ExaEarn Affiliate Clawbacks

## Pre-Payout Reversal

If the source event reverses while commission is PENDING, HELD or AVAILABLE:

```text
commission -> REVERSED
clawback -> APPLIED
```

No payout is made.

## Post-Payout Reversal

If the source event reverses after payout:

```text
commission -> CLAWBACK_PENDING
clawback -> PENDING
```

The system records an explicit obligation. It does not silently edit historical payout records and does not push user wallets negative.

## Idempotency

Clawbacks are unique by:

```text
affiliate_commission_event_id + reversal_reference
```
