# ExaEarn Restart Recovery

Phase 7 builds on earlier restart/replay work:

- Spot execution journal and replay
- Spot settlement outbox retry
- Market-data rebuild from authoritative trades/events
- Margin durable realtime event replay
- Margin readiness/load probes
- unified financial reconciliation after restart

Phase 7 adds persisted readiness checks and load runs so restart recovery has an operational audit trail.

