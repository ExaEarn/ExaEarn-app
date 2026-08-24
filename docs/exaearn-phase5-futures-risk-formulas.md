# ExaEarn Phase 5 Futures Risk Formulas

All formulas use `FinancialDecimal`; no PHP float fallback is allowed.

## Linear USDT Perpetual

```text
notional = price * quantity
```

## Initial Margin

```text
initial_margin = notional / leverage + fee_buffer
```

The selected leverage must not exceed the market risk tier.

## Maintenance Margin

```text
maintenance_margin = notional * tier_maintenance_margin_rate + tier_maintenance_amount
```

## Unrealized PnL

Long:

```text
unrealized_pnl = quantity * (mark_price - entry_price)
```

Short:

```text
unrealized_pnl = quantity * (entry_price - mark_price)
```

## Liquidation Price

Long:

```text
liquidation_price = entry_price - ((margin - maintenance_margin) / quantity)
```

Short:

```text
liquidation_price = entry_price + ((margin - maintenance_margin) / quantity)
```

## Bankruptcy Price

Long:

```text
bankruptcy_price = entry_price - (margin / quantity)
```

Short:

```text
bankruptcy_price = entry_price + (margin / quantity)
```

## Funding

```text
premium_rate = (mark_price - index_price) / index_price
funding_rate = clamp(premium_rate + interest_rate, min_rate, max_rate)
payment = abs(funding_rate) * mark_price * quantity
```
