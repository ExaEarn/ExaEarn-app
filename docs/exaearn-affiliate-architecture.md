# ExaEarn Affiliate Architecture

Affiliate / Referral / Rewards now follows the existing ExaEarn financial architecture rather than a separate reward ledger.

## Components

- `ReferralService`: owns referral binding and legacy qualified activity compatibility.
- `AffiliateCommissionService`: central commission event, hold, payout, reversal and reconciliation workflow.
- `RewardPolicyEngine`: preferred policy calculator for affiliate reward decisions.
- `AffiliateTier`: configurable tier fallback when no active reward policy exists.
- `ExaPointService`: active non-cash reward instrument.
- Admin Affiliate Center APIs: operational read/review endpoints.

## Source Of Truth

Affiliate commissions are read-model obligations derived from authoritative product events. Real financial payout rails must settle through canonical settlement/ledger infrastructure before being enabled.

ExaPoints are not represented as fiat/crypto liabilities by default.

## Event Flow

```text
Settled product event
  -> commissionable event registry
  -> referral relationship
  -> RewardPolicyEngine / tier policy
  -> fraud/security checks
  -> PENDING or HELD commission
  -> AVAILABLE after hold
  -> EXAPOINT payout
  -> reversal/clawback if underlying event reverses
```

## Active Integration

`subscription_purchase` from ExaAI is mapped to `EXAAI:SUBSCRIPTION_PURCHASE`.

Other product event types are present in the registry but disabled until their authoritative fee/revenue events are explicitly connected.
