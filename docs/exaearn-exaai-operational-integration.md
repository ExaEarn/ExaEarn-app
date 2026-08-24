# ExaEarn ExaAI Operational Integration

## Integrated Systems

ExaAI remains integrated with:

- Phase 1 canonical ledger
- Spot OMS and matching
- Futures OMS and risk
- Phase 13 operations service
- Phase 16 compliance
- Phase 17 finance/reconciliation
- Phase 18 security
- Phase 19 reliability/SRE

## Fail-Safe States

Global operational states:

- NORMAL
- NEW_RISK_DISABLED
- REDUCE_ONLY
- PAUSED
- EMERGENCY

Higher-risk global states override local strategy state.

## Recovery

Safe resume requires healthy dependencies and no unresolved critical incidents. Critical financial/accounting incidents must not auto-resume without operational validation.
