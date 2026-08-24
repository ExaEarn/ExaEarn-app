# ExaEarn Pricing Policy Engine

## Purpose

The Pricing Policy Engine is the single commercial decision layer for fees, spreads, rebates and pricing snapshots. It does not move money and does not replace ledger settlement.

## Runtime Flow

```text
Product operation
    ↓
Pricing context normalization
    ↓
Active approved rule selection
    ↓
Deterministic precedence
    ↓
Guardrails
    ↓
Pricing decision snapshot
    ↓
Settlement/ledger consumes the snapshot
```

## Rule Dimensions

Rules can target:

- Product and operation
- Asset/currency/network
- Market symbol
- Country
- VIP tier
- Merchant tier
- User contract
- Institution contract
- Promotion code

## Quote Snapshots

`pricing_decisions` store immutable snapshots containing the selected rule version, fee amount, rebate amount, net amount, context and expiry. Financial services should store the decision UUID or snapshot in their transaction metadata.

## Migration Strategy

Current compatibility mode:

- Existing `FeeCalculator` methods keep their historical return shape.
- If an approved central rule exists, the engine can replace the calculated fee.
- If shadow mode is enabled, legacy fee remains active and the engine records comparison data.
- If no central rule exists, legacy config remains the compatibility fallback.

Recommended hardening order:

1. Seed product-default rules from current production config.
2. Run shadow mode and monitor `pricing_shadow_comparisons`.
3. Approve product-by-product enforcement.
4. Require pricing decision UUIDs in settlement metadata.
5. Remove direct config fee reads after reconciliation is clean.
