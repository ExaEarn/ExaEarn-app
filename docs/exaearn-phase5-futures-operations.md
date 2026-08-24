# ExaEarn Phase 5 Futures Operations

## Health Checks

Track:

- Futures OMS
- mark/index age
- funding rate generation
- funding settlement failures
- liquidation events
- insurance fund balance
- ADL queue
- reconciliation findings
- settlement backlog

## Kill Switches

Use:

- `FUTURES_ENABLED=false`
- market `status != active`
- `FUTURES_ALLOW_EXTERNAL_EXECUTION=false`

## Production Guard

External Futures execution is disabled by default. ExaEarn Futures risk/accounting must remain internal authority.
