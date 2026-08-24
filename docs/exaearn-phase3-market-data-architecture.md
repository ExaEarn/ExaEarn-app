# ExaEarn Phase 3 Market Data Architecture

## Objective

Phase 3 establishes a single backend market-data layer for ExaEarn Spot markets.

```text
ExaEarn Matching Engine
  -> Trades / Open Orders / Book Snapshots / Realtime Events
  -> MarketDataService
  -> REST + stream snapshot/deltas
  -> Web + Mobile + future developer API
```

## Core Services

- `MarketDataService`: normalized market contract, tickers, order book, recent trades, candles, health, stream snapshots.
- `ExternalReferenceMarketDataService`: Binance-backed reference adapter used only when internal ExaEarn data is unavailable.
- `SpotRealtimeSequenceService`: sequenced market-data deltas with gap detection.
- `backend/services/blockchain-service/src/services/marketStreamHub.js`: public `/ws/markets` fanout supporting Phase 3 topic subscriptions.

## Source Separation

Internal fields use `source = EXAEARN_INTERNAL`.

Fallback fields use `source = EXTERNAL_REFERENCE`, `source_type = reference`, `is_internal = false`, and `reference_provider = BINANCE`.

This lets ExaEarn keep a useful product experience while preserving market integrity. Provider depth/trades are never silently counted as ExaEarn internal activity.

## REST API

- `GET /api/v1/market/symbols`
- `GET /api/v1/market/tickers`
- `GET /api/v1/market/ticker/{symbol}`
- `GET /api/v1/market/order-book/{symbol}`
- `GET /api/v1/market/trades/{symbol}`
- `GET /api/v1/market/klines/{symbol}`
- `GET /api/v1/market/deltas/{symbol}`
- `GET /api/v1/market/stream/snapshot`
- `GET /api/v1/market/health`

Legacy authenticated `/api/trade/markets`, `/api/trade/order-book`, `/api/trade/trades`, and `/api/trade/candles` remain compatible but now read through `MarketDataService`.

## Persistence

Authoritative data remains in:

- `orders`
- `trades`
- `spot_order_book_snapshots`
- `spot_market_data_events`

Candles and tickers are currently read models computed from trades. They remain reconstructable from authoritative trade history.

## Public WebSocket

The existing Node market stream hub now accepts:

```json
{"op":"subscribe","topics":["market.BTCUSDT.ticker","market.BTCUSDT.book"]}
```

It also supports heartbeat ping/pong, subscription limits, and legacy channel/pair compatibility.

Laravel publishes `SpotMarketDataEvent` payloads to `streaming.market_channel`, which the Node service can subscribe to through Redis or receive through the existing service publish endpoint.

## Frontend Migration

The web market-data service no longer performs browser-side Binance fallbacks. It consumes `/api/v1/market/*`.

The React Native market and trade screens now consume `/api/v1/market/*` instead of generated/fake market data.
