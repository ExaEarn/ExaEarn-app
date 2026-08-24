# ExaEarn Phase 19 Service Registry

The SRE service registry tracks production service identity and dependency metadata.

## Fields

- `service_id`
- `service_name`
- `service_type`
- `criticality`
- `environment`
- `version`
- `deployment_id`
- `region`
- `dependencies`
- `health_endpoint`
- `readiness_endpoint`
- `heartbeat_at`
- `last_seen_at`
- `status`

## Core Services Seeded

- API gateway
- Canonical ledger
- Spot trading engine
- Custody operations
- Fiat rails
- Market data
- Security operations
- Finance reconciliation

Criticality uses `TIER_0` through `TIER_3`.

