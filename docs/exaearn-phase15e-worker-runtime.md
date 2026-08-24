# ExaEarn Phase 15E Worker Runtime

Bot runtime is separate from HTTP request handling.

`MarketMakerBotService::acquireLease()` provides durable worker ownership:

- one worker controls a bot during its lease
- competing workers fail until the lease expires
- restart requires lease recovery, inventory checks and order reconciliation before live quoting

HTTP endpoints remain a control plane.
