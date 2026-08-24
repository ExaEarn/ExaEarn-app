# ExaEarn Phase 13 Load Testing

`ExaAiLoadTestService` records real load runs in `exaai_load_runs`.

Readiness checks require persisted successful runs for:

- `exaai_1k_decisions`
- `exaai_10k_decisions`

A run only passes when:

- failed decisions = 0
- duplicate decisions = 0
- financial invariant failures = 0

Local tests persist representative passed runs to verify the readiness gate calculation. Production launch requires environment-specific load execution with real queue/database topology.
