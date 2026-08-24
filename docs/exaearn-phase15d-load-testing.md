# ExaEarn Phase 15D Load Testing

The focused local test validates core RFQ correctness and financial invariants. 1K concurrent RFQ load was not run in this local environment.

## Validated Locally

- idempotent RFQ request
- idempotent acceptance
- internal MM settlement
- realtime replay
- reconciliation
- cross-institution protection

## Not Run

- 1K concurrent RFQ load
- external provider outage fanout
- production settlement queue pressure

These require production-like queue/Redis/provider infrastructure.
