# ExaEarn Lending Pool Risk

`LendingPoolRiskService` evaluates Margin lending-pool health.

## Signals

- total liquidity
- available liquidity
- borrowed liquidity
- utilization bps
- pool status

## States

- `HEALTHY`
- `RESTRICTED`
- `BORROW_DISABLED`
- `DEFICIT`

Deficits make operational readiness `NOT_READY`.

