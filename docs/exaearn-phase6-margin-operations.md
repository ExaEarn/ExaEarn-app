# ExaEarn Phase 6 Margin Operations

Operational requirements before production enablement:

- fund real lending pools
- enable assets individually
- validate reference/index prices
- run reconciliation regularly
- add alerting for pool mismatch, bad debt, stale pricing, and liquidation backlog
- keep feature flag at `disabled`, `internal`, or `shadow` until operational readiness is verified

Production Margin must fail closed when critical dependencies are unavailable.
