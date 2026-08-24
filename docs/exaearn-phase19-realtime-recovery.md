# ExaEarn Phase 19 Realtime Recovery

ExaEarn reuses the existing public/private realtime and replay systems from earlier phases. Phase 19 treats WebSocket as notification infrastructure only.

If realtime fails:

1. Do not create financial effects from replay.
2. Clients resync using snapshots and replay endpoints.
3. Slow consumers are disconnected by the existing stream contracts.
4. Database, ledger, OMS and read models remain authoritative.

