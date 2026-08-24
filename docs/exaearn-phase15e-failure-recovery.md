# ExaEarn Phase 15E Failure Recovery

Fail-closed behavior:

- unapproved live mode is rejected
- stale market data blocks quoting
- reduce-only blocks new live quotes
- risk-block incidents persist outside rolled-back quote-cycle transactions
- idempotency keys prevent duplicate quote cycles

Unknown order recovery must query authoritative order state using bot client order IDs before retrying.
