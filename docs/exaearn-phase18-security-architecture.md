# ExaEarn Phase 18 Security Architecture

Phase 18 introduces a central security operations control plane:

Identity + Device + Session + IP + Transaction + Withdrawal + API + Trading + Product + Compliance + Finance signals feed `SecurityRiskEngine`.

The engine produces:

- `ALLOW`
- `ALLOW_WITH_MONITORING`
- `MFA_REQUIRED`
- `TEMPORARY_HOLD`
- `MANUAL_REVIEW`
- `BLOCK`
- `EMERGENCY_LOCK`

New persistent records:

- `security_risk_signals`
- `security_risk_decisions`
- `security_cases`
- `security_incidents`
- `security_rules`
- `security_emergency_controls`
- `security_related_accounts`
- `security_withdrawal_addresses`

Legacy `security_events`, audit logs, fraud logs, Sanctum sessions, developer API logs, Phase 16 compliance cases and Phase 17 finance breaks remain in use.
