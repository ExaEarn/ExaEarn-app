# ExaEarn Phase 18 Withdrawal Security

`WithdrawalSecurityService` evaluates:

- new withdrawal addresses
- address first seen / last used
- velocity
- large amount threshold
- recent security cooldowns
- active finance reconciliation breaks
- emergency controls
- fail-closed status

Decisions are persisted in `security_risk_decisions`. Address values are stored as hashes in `security_withdrawal_addresses`.
