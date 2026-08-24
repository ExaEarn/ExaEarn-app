# ExaEarn Phase 15E Preimplementation Audit

## KEEP

- Phase 15C market-maker profiles, assignments, safety mode, inventory snapshots, liquidity agreements and admin controls.
- Phase 14 developer API/security architecture as the intended external execution boundary.
- Spot `TradeService`, `TradingRiskEngine`, `ReservationService`, `SettlementService` and canonical ledger.
- Phase 3 `MarketDataService` as trusted market-data input.
- Institutional subaccounts and RBAC from Phase 15B.
- Phase 15D explicit OTC LP enablement and information barrier.

## HARDEN

- Bot safety incidents must persist even when quote-cycle creation fails.
- Live bot quoting must be gated by bot state, MM profile state, market state and market-data freshness.
- Worker ownership must use leases before runtime workers control a bot.

## CONSOLIDATE

- Bot realtime uses `InstitutionalRealtimeService`, not a new stream store.
- Bot inventory reads use Phase 15C `MarketMakerInventoryService`.
- Bot orders carry attribution metadata and use existing order services.

## MISSING BEFORE THIS PHASE

- Durable bot identity, strategy versions, quote cycles, bot order attribution, bot incidents and bot performance snapshots.
- Bot fair-value/spread/quote/risk services.
- Institutional and admin bot APIs.

## NOT APPLICABLE IN THIS SLICE

- Production external hedging credentials.
- Public automated MM operations staffing.
- Real MM bot operating capital funding.
