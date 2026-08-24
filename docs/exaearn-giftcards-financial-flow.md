# ExaEarn Giftcards Financial Flow

## Buy Flow

```text
User funding account
  -> ReservationService reserve
  -> provider/inventory fulfillment
  -> SettlementService::giftcardPurchaseSettle
  -> LedgerService double-entry transaction
  -> ReservationService consume
```

Settlement debits the reserved user funding account and credits:

- `giftcard_provider_settlement`
- `giftcard_fee_revenue` when a fee is charged

## Sell Flow

```text
Approved giftcard submission
  -> SettlementService::giftcardSellPayout
  -> LedgerService double-entry transaction
```

Settlement debits `giftcard_payout_treasury` and credits the user's funding account.

## Refund Flow

Refunds use `SettlementService::giftcardRefundCredit` and are idempotent by `giftcard_refund:{order_id}`. They do not directly restore wallet rows.

