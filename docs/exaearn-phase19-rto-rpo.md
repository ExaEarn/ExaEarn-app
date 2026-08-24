# ExaEarn Phase 19 RTO/RPO

## Target Framework

| Service Class | Example | RTO Target | RPO Target |
| --- | --- | --- | --- |
| Tier 0 | Ledger, database, custody state | Minutes | Near-zero with PITR |
| Tier 1 | Trading, risk, market data | Minutes | Durable event recovery |
| Tier 2 | Fiat, P2P, reporting | Hours | Durable DB records |
| Tier 3 | Notifications, analytics | Hours | Best effort |

Actual production RTO/RPO requires deployed HA, backups and restore drills.

