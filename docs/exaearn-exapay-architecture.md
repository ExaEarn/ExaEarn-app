# ExaEarn ExaPay Architecture

ExaPay is the merchant payment acceptance product. It is separate from ordinary wallet transfer, fiat deposit, Spot trading, P2P escrow and ExaCard, while reusing the same canonical financial primitives.

## Components

- `ExaPayMerchantService`: merchant onboarding, intent orchestration, payment links, hosted checkout, API keys, refunds, disputes, settlement and reconciliation.
- `ExaEarnPayService`: existing Phase 10 payment intent and capture service, extended for merchant intents.
- `SettlementService`: canonical capture through `exaearnPayTransfer`.
- `PaymentRefundService`: canonical ledger reversal for refunds.
- `PaymentDisputeService`: dispute case creation.
- `MerchantSettlementService`: merchant settlement batch read model.
- `DeveloperWebhookService`: existing Phase 14 webhook signing, retry, dead-letter and replay.

## Product Boundary

ExaPay records merchant payment activity in `exaearn_pay_intents`, `merchant_payment_links`, `merchant_settlements`, `payment_refunds`, `payment_disputes`, `merchant_webhook_events` and reconciliation tables.

Financial authority remains the canonical ledger.

## Environments

Merchant records, API keys, payment links and payment intents carry `SANDBOX` or `PRODUCTION`. Sandbox provider operation is software-ready. Production provider/banking activation remains external.
