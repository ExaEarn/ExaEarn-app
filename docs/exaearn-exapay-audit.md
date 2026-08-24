# ExaEarn ExaPay / Payments Audit

## Maturity

Current level: Level 2 to Level 3 software foundation, depending on sub-flow.

## What Exists

- Phase 10 fiat/payment infrastructure and tests.
- `ExaEarnPayService` creates intents and captures through `SettlementService`.
- Payment provider router, webhook, refund, dispute, health, and sandbox provider services exist.
- Fiat deposit and withdrawal flows have provider abstractions and tests covering idempotency, webhook signatures, settlement, failed withdrawal release, refund reversal, and readiness.

## Gaps

- ExaPay merchant/product UX was not found as a complete standalone merchant dashboard.
- Production provider credentials, bank/payment processor operations, settlement files, disputes, and chargeback handling remain external/operational dependencies.
- Withdrawal APIs should remain gated until explicit security controls are enabled.

## Required Next Work

1. Complete merchant onboarding, API keys, hosted checkout/payment links, refunds, disputes, webhooks, reporting, and merchant settlement UX.
2. Keep capture/refund on canonical settlement.
3. Add merchant risk, KYB, transaction monitoring, and reconciliation dashboards.

