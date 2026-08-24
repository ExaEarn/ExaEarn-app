# ExaEarn Phase 15A Launch Automation

Scheduling creates durable launch events:

- `ANNOUNCEMENT`
- `DEPOSIT_OPEN`
- `TRADING_OPEN`
- `WITHDRAWAL_OPEN`

Events have stable idempotency keys and can be retried after scheduler downtime. Trading launch reruns final readiness checks and blocks if a critical dependency fails.

Deposits, trading and withdrawals can open at different times.

