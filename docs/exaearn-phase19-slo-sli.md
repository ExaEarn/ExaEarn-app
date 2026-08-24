# ExaEarn Phase 19 SLO and SLI Framework

## SLIs

- API liveness and readiness
- database dependency health
- queue depth and oldest job age
- worker heartbeat freshness
- active critical incidents
- backup freshness and restore-test status
- market-data freshness
- RPC provider validity

## SLO Direction

- Tier 0 financial/control services require the strictest availability and recovery objectives.
- Non-critical user-experience services may degrade before financial services.
- Error budgets must never authorize unsafe financial state.

## Software Representation

Phase 19 stores active SLO definitions in `sre_slo_definitions`. Core definitions cover API readiness, ledger invariants, spot-engine latency, market-data freshness, RPC wrong-chain protection, finance reconciliation and security fail-closed availability.
