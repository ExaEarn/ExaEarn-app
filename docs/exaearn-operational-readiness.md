# ExaEarn Operational Readiness

`ExchangeOperationalReadinessService` evaluates exchange component health.

## Components

- database
- Redis
- ledger
- Spot
- Futures
- Margin
- price protection
- circuit breakers
- lending
- liquidation
- treasury
- wallets
- realtime
- queues
- reconciliation
- risk engine

## Statuses

- `READY`
- `DEGRADED`
- `NOT_READY`

Checks are persisted in `operational_readiness_checks`.

An active global `EMERGENCY_STOP` makes readiness `NOT_READY`.

