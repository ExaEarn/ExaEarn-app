# ExaEarn Insurance Fund

`InsuranceFundService` adds a general operational insurance-fund read/write model.

## Tables

- `insurance_fund_accounts`
- `insurance_fund_transactions`

## Behavior

- creates one fund account per product/asset
- credits are idempotent by reference
- usage/debits are idempotent by reference
- debit fails if the fund balance is insufficient
- no fabricated balances are created

Futures-specific insurance logic can continue to use its existing service while operations receives a common fund model.

