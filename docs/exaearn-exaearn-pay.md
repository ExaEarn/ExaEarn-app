# ExaEarn Pay

ExaEarn Pay foundation supports:

- payment intent creation
- payer and recipient user references
- amount, currency and fee tracking
- canonical ledger capture through `SettlementService::exaearnPayTransfer`
- refund/reversal through `PaymentRefundService`

Merchant tables are included for merchant onboarding and future settlement cycles. No production merchant acquiring provider is marked live by Phase 10.
