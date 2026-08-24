# ExaEarn Phase 6 Margin Accounting

## Ledger Account Types

- User cross margin: `margin_cross`
- User isolated margin: `margin_isolated_btc_usdt`, etc.
- System lending pool: `margin_lending_pool`
- Margin reserve fund: `margin_reserve_fund`
- Margin interest revenue: `margin_interest_revenue`

## Borrow Settlement

```text
Dr margin_lending_pool
Cr user margin account
```

Loan principal is recorded in `margin_loans`.

## Repay Settlement

```text
Dr user margin account
Cr margin_lending_pool        principal
Cr margin_reserve_fund        reserve share of interest
Cr margin_interest_revenue    remaining interest
```

Repayment applies to interest first, then principal.
