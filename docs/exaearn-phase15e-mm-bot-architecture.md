# ExaEarn Phase 15E Market-Maker Bot Architecture

The MM bot is an approved market-maker client automation layer. It does not insert liquidity directly into the order book and does not fabricate fills or volume.

Flow:

```text
MarketDataService
-> MarketMakerFairValueService
-> MarketMakerSpreadService
-> MarketMakerQuoteEngine
-> MarketMakerBotRiskService
-> MarketMakerBotService
-> TradeService / OMS
-> reservations / settlement / ledger
```

Every bot belongs to an institution, Phase 15C market-maker profile, institutional subaccount, strategy and strategy version. Live Spot quotes are submitted through `TradeService` with bot/strategy/quote-cycle metadata.

Shadow mode records quote cycles only and creates no orders.
