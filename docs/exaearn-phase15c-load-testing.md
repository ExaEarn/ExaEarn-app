# ExaEarn Phase 15C Load Testing

The focused Phase 15C test validates the program, ledger and control invariants. Large external venue / many-market quote storms were not run in this local pass.

## Local Validated

- Application idempotency.
- Maker-checker activation.
- Assignment and agreement creation.
- Capital readiness.
- Inventory snapshot generation.
- Health snapshot generation.
- Rebate settlement idempotency.
- Mass quote cancellation.

## Not Run Locally

- Multi-venue external liquidity load.
- 10K API key market-maker quote storm.
- Production venue failover.

These require production-like Redis, queue workers, market data fanout and venue sandbox credentials.
