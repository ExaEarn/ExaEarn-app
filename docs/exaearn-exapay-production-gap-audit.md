# ExaEarn ExaPay Production Gap Audit

## Scope

This audit rechecked the existing ExaPay/Phase 10 payment foundation before adding the merchant platform layer.

## Current Architecture

| Area | Status | Notes |
| --- | --- | --- |
| Phase 10 fiat/payment infrastructure | READY | Provider router, provider health, sandbox provider, fiat deposits/withdrawals, provider webhooks and readiness services exist. |
| ExaEarnPayService | READY | Payment capture continues through `SettlementService::exaearnPayTransfer`. |
| Canonical ledger | READY | Captures and refunds use ledger settlement/reversal services. |
| Merchant organization | READY | Existing `merchants` table extended with KYB, risk, environment, pricing and settlement fields. |
| Merchant KYB | READY | Software gate supports KYB-approved activation; external/business review remains operational. |
| Merchant team RBAC | READY | Owner/team role and permission model added. |
| Merchant API keys | READY | Merchant keys are scoped, revocable and environment-specific; Phase 14 developer permissions include ExaPay scopes. |
| Hosted checkout | READY | Checkout token is unguessable, hash-stored, expiring and bound to a payment intent. |
| Payment links | READY | Links create real payment intents per payment and enforce status/expiry/use limits. |
| Refunds | READY | Refunds use canonical ledger reversal and are idempotent. |
| Disputes | READY | Merchant-facing dispute records and webhook events are supported. |
| Merchant settlement | READY | Settlement batches aggregate captured payments; external payout operations remain gated. |
| Merchant webhooks | READY | Merchant events enqueue through existing Phase 14 webhook delivery/signing/replay infrastructure. |
| Reconciliation | READY | Merchant reconciliation detects captured-without-ledger and duplicate ledger references. |
| Admin ExaPay center | READY | Admin APIs and admin module wiring cover merchants, reports, provider health and reconciliation. |
| Merchant dashboard | READY | Web app exposes merchant onboarding, payment links, hosted checkout token creation, API keys and payment summaries. |
| Real payment provider credentials | EXTERNAL_REQUIREMENT | Production credentials and provider go-live are not fabricated. |
| Real bank/processor settlement | EXTERNAL_REQUIREMENT | Requires configured rails, settlement accounts and operational validation. |
| Chargeback operations | EXTERNAL_REQUIREMENT | Software dispute/reserve hooks exist; network/provider chargeback operations require external setup. |
| Tax/legal policy | EXTERNAL_REQUIREMENT | Fields exist for tax/invoice metadata; legal treatment requires external review. |

## Money Flow

Merchant and payer activity now follows:

```text
Merchant
-> KYB/risk gate
-> Payment intent or payment link
-> Hosted checkout/API capture
-> SettlementService
-> Canonical ledger transaction
-> Merchant payable read model
-> Refund/dispute/settlement controls
-> Reconciliation/reporting
```

No merchant wallet or balance column was introduced as financial truth.
