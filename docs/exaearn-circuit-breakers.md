# ExaEarn Circuit Breakers

`CircuitBreakerService` provides durable global/product/market states.

## States

- `NORMAL`
- `WARNING`
- `RESTRICTED`
- `CANCEL_ONLY`
- `REDUCE_ONLY`
- `PAUSED`
- `EMERGENCY_STOP`

## Behavior

- `NORMAL` and `WARNING` allow new risk.
- `CANCEL_ONLY`, `PAUSED` and `EMERGENCY_STOP` reject new orders.
- `REDUCE_ONLY` allows reduce-only Futures orders.
- Market transitions also update `trading_market_states`.
- Market pause/resume admin APIs update Spot `trading_status`.
- Every transition is recorded in `trading_operational_audit_logs`.

