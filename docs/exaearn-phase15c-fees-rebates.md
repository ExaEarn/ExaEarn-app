# ExaEarn Phase 15C Fees And Rebates

Market-maker rebate accrual and payout are separated.

## Accrual

`MarketMakerRebateService::accrue()` records the eligible maker volume and calculated rebate for a period.

## Payment

`MarketMakerRebateService::pay()` settles the rebate through `LedgerService::postDoubleEntry()`.

```text
market_maker_rebate_pool -> institutional MARKET_MAKER subaccount
```

The rebate period stores a stable `settlement_reference` and repeated payment calls are idempotent.
