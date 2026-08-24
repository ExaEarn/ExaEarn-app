# ExaEarn Giftcards Production Gap Audit

## Scope

This audit covers the existing Giftcards implementation in `backend/api-gateway/app/Services/GiftcardService.php` and `backend/api-gateway/app/Services/GiftCard/*`.

## Findings And Actions

| Area | Previous State | Action |
| --- | --- | --- |
| Buy purchase settlement | Direct legacy wallet mutation and legacy `TransactionService` paths existed. | Migrated Giftcard buy paths to `ReservationService` and `SettlementService::giftcardPurchaseSettle`. |
| Sell payout | Seller payouts credited wallet/legacy ledger directly. | Migrated seller payout to `SettlementService::giftcardSellPayout`. |
| Refund | Refund restored wallet balance directly in `GiftCardPurchaseService`. | Migrated refund to idempotent `SettlementService::giftcardRefundCredit`. |
| Provider integration | Purchase service simulated successful fulfillment. | Added `GiftCardProviderInterface`, `GiftCardProviderManager`, and sandbox provider guarded from production. |
| Provider unknown state | No durable unknown-state handling. | `PROVIDER_UNKNOWN` keeps order in `provider_unknown` with active reservation and provider reference. |
| Delivery notification | Email delivery was TODO/log-only. | Delivery now creates a secure-reveal notification through ExaEarn `NotificationService`. |
| Treasury visibility | No Giftcard treasury account overview. | Added `GiftCardTreasuryService` for provider settlement, payout treasury, fee revenue, and refund liability accounts. |
| Reconciliation | No Giftcard-specific reconciliation pass. | Added `GiftCardReconciliationService` for completed order settlement checks and duplicate delivery detection. |
| Decimal handling | Fee calculations mixed BCMath with float internals. | Fee calculator now performs core arithmetic through `FinancialDecimal`/BCMath strings while preserving legacy response compatibility. |

## Remaining Legacy Compatibility

Legacy wallet rows may still seed canonical funding accounts for existing users. They are treated as compatibility projections and are no longer mutated by Giftcard purchase, delivery, payout, or refund flows.

Risk/fraud score calculations still use non-money float scores. This is acceptable because those values are risk scores, not financial balances or settlement values.

