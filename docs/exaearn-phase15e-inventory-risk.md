# ExaEarn Phase 15E Inventory Risk

Inventory uses Phase 15C `MarketMakerInventoryService` and canonical institutional subaccount ledger projections.

Risk gates check:

- market-maker profile status
- profile safety mode
- bot state
- bot safety state
- market status
- market-data freshness
- inventory status
- configured loss controls

Blocked quote attempts create `market_maker_bot_incidents`.
