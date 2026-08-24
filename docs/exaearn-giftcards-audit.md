# ExaEarn Giftcards Audit

## Maturity

Current level: Level 2 functional, not production software ready.

## What Exists

- Web sell/buy flows in `apps/web/src/Giftcard` and `apps/web/src/BuyGiftcard`.
- API client in `apps/web/src/services/giftcardApi.js`.
- Laravel controllers: `GiftcardController`, `GiftCardBuyController`, `AdminGiftCardBuyController`, `GiftCardAdminController`.
- Services for rates, pricing, inventory, fraud, delivery, buy, sell, and purchase.
- Tables for giftcards, inventory, rates, orders, submissions, and fraud flags.
- Feature tests for buy/sell, auto decisions, purchase fees, refund, ledger entries, and reports.

## Production Blockers

- `GiftCardPurchaseService` accepts money as floats and directly deducts `Wallet.available_balance`.
- `GiftCardBuyService` directly mutates `available_balance` and `locked_balance`.
- Provider purchase is explicitly simulated in `GiftCardPurchaseService`.
- Email delivery still has a TODO in `GiftCardDeliveryService`.
- Treasury account handling still has a TODO in `GiftCardBuyService`.
- Refund path can complete wallet restoration even if ledger refund recording fails, creating possible ledger/wallet divergence.

## Required Next Work

1. Route all giftcard purchases, sales, refunds, and payouts through canonical reservations and `SettlementService`.
2. Remove float parameters from financial APIs.
3. Replace simulated provider execution with provider adapters that fail closed in production.
4. Add provider reconciliation, delivery audit, inventory reconciliation, and treasury settlement jobs.
5. Add admin review workflow for provider failures and suspicious orders.

