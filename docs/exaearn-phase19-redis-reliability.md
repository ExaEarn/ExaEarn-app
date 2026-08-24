# ExaEarn Phase 19 Redis Reliability

Redis responsibilities include cache, locks, realtime fanout, sessions where configured, market-data distribution and queue coordination when enabled.

Phase 19 records Redis health as a dependency. If Redis is unavailable, new-risk systems must degrade or fail closed according to the product control plane. Financial state remains in PostgreSQL and the canonical ledger, not Redis.

Production Redis HA remains OPERATIONAL SETUP REQUIRED until a managed Redis HA service or equivalent is configured and validated.

