# ExaEarn Trading Risk Engine

`TradingRiskEngine` is the Phase 7 unified pre-trade risk layer.

## Products Covered

- Spot
- Margin
- Futures

## Decisions

The service returns structured decisions with:

- `status`
- `reason_code`
- `action`
- `metadata`
- `evaluated_at`

Implemented actions include `ALLOW` and `REJECT`.

## Checks Implemented

- global/product/market circuit breaker state
- user risk profile status
- market trading status
- max order notional
- price protection for Spot/Margin markets
- Margin lending-pool availability for Auto Borrow shortfall
- Futures leverage limit

Product-specific risk engines remain active after the unified gate, so Phase 7 adds a common fail-closed layer without removing deeper existing checks.

