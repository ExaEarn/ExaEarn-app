# ExaEarn Giftcards Architecture

Giftcards now follows ExaEarn's canonical financial architecture:

```text
Giftcard request
  -> fraud/risk checks
  -> canonical reservation
  -> provider or inventory fulfillment
  -> settlement service
  -> double-entry ledger
  -> delivery notification / secure reveal
  -> reconciliation
```

## Main Components

- `GiftCardPurchaseService`: provider-backed buy flow.
- `GiftcardService`: legacy route-compatible marketplace buy/sell flow.
- `GiftCardBuyService`: inventory-backed buy flow.
- `GiftCardSellService`: submission payout flow.
- `GiftCardProviderManager`: resolves configured provider adapter.
- `GiftCardProviderInterface`: provider contract for purchase/status/refund/balance.
- `GiftCardTreasuryService`: Giftcard treasury/readiness visibility.
- `GiftCardReconciliationService`: order, ledger, and delivery consistency checks.

## Production Rule

Giftcards must not mutate wallet balances directly. User funds are reserved before fulfillment, consumed only on successful settlement, and released when fulfillment cannot proceed.

