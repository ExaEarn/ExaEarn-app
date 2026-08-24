# ExaEarn Phase 19 PostgreSQL HA

## Software Capability

READY. ExaEarn now has database dependency checks, production configuration validation and safe recovery prechecks.

## Production Deployment

OPERATIONAL SETUP REQUIRED. A real production PostgreSQL primary/standby or managed HA cluster must be configured outside this local repository.

## Recovery Contract

Database recovery must follow:

1. Detect outage.
2. Stop unsafe writes.
3. Enter degraded/safe mode.
4. Restore connectivity or promote standby.
5. Validate database.
6. Run ledger and finance reconciliation.
7. Check security readiness.
8. Resume in safe mode.

Do not resume normal financial operations solely because SQL connections work again.

