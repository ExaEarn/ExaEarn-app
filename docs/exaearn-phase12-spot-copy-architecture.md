# ExaEarn Phase 12 Spot Copy Architecture

Spot copy trading copies authoritative lead fills, never lead balances or database positions.

```text
Lead spot fill
-> copy_lead_trade_events(product=spot)
-> copy fanout
-> follower sizing and risk checks
-> TradeService::placeOrder
-> Spot OMS / matching / settlement
-> follower ledger balances
-> copy strategy attribution
```

## Buy Flow

For a copied spot buy, the follower copy amount is sized from the relationship settings:

- `fixed_amount`
- `fixed_ratio`
- `proportional`

The copy service submits a real follower limit IOC order through `TradeService`. The limit price includes the configured copy slippage tolerance. If ExaEarn liquidity cannot fill within the bound, the copy order is marked `SKIPPED_SLIPPAGE_LIMIT`; no fake fill is created.

## Sell Flow

For a copied spot sell, the copy service sells only the quantity attributed to that lead relationship in `copy_strategy_positions`. It does not sell unrelated manual spot holdings or assets attributed to another lead.

## Attribution

`copy_strategy_positions` tracks the strategy layer:

- relationship
- lead trader
- follower
- product
- symbol
- asset
- attributed quantity
- remaining quantity
- cost basis
- realized PnL

The normal ExaEarn ledger remains authoritative for actual balances.
