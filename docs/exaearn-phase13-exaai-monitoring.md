# ExaEarn Phase 13 ExaAI Monitoring

The operations service records component metrics in `exaai_operational_metrics`.

Tracked components:

- database
- Redis
- market data
- Spot OMS
- Futures OMS
- risk engine
- liquidity
- queue
- realtime
- reconciliation
- surveillance

Operational alerts support deduplication, acknowledgement fields, resolution and severity levels.

Readiness endpoint:

```text
GET /api/admin/exaai/operations/readiness
```
