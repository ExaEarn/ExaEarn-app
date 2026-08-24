# ExaEarn Phase 5B Cross Margin

Date: 2026-08-18

`CrossMarginHealthService` is the server-authoritative cross-margin account view.

## Formula

```text
equity =
cash_balance
+ realized_pnl
+ unrealized_pnl
+ funding_accrual
- fees_due
```

```text
available_margin =
equity
- position_initial_margin
- active_order_reservations
```

```text
maintenance_margin = sum(position_maintenance_margin_by_risk_tier)
margin_ratio = equity / maintenance_margin
```

## Risk States

- `HEALTHY`
- `WARNING`
- `LIQUIDATION_PENDING`
- `BANKRUPT`

## Policy

Only the Futures ledger account is used for Futures cross collateral. Funding, Spot, Earn and other balances are not consumed by cross margin unless explicitly transferred into Futures.
