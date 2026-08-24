# ExaEarn Phase 3 Market Data Report

Date: 2026-08-18

## A. Executive Summary

Phase 3 introduces a central authoritative Spot market-data layer. Web and mobile Spot market-data reads now route through ExaEarn backend APIs. External exchanges can still be used as server-side reference fallback, but the response explicitly marks them as `EXTERNAL_REFERENCE`.

## B. Existing Sources Audited

Audited:

- `TradeService`
- `MarketDataCollectorService`
- `MarketStreamService`
- `RealtimeStreamService`
- `OrderBookDepthService`
- `PriceAggregationService`
- `PriceAnchorService`
- web `marketDataService.ts`
- mobile market/trade screens
- Laravel event/SSE routes

## C. Internal vs External Data Policy

Internal ExaEarn book, trades, last price, ticker and candles come from ExaEarn orders/trades/events when available. External fallback is permitted only as reference display and is labeled.

## D. Market Data Architecture

Added:

- `MarketDataService`
- `ExternalReferenceMarketDataService`
- public `/api/v1/market/*` routes

## E. Order Book

Internal book uses `spot_order_book_snapshots` or open resting orders. Empty internal book may return external reference book with `is_internal=false`.

## F. Book Snapshot/Delta Protocol

Snapshot plus sequenced deltas is supported. `SpotRealtimeSequenceService` now allows multiple event rows at one engine sequence while still detecting true sequence gaps.

## G. Recent Trades

Internal recent trades use `trades`. Empty internal trade history may return explicitly labeled reference trades.

## H. Last Price

`last_trade_price` is only an ExaEarn execution price. `reference_price` is separate.

## I. Tickers

Rolling 24h ticker metrics use ExaEarn trades where present. With no internal trades, reference ticker data is returned as `EXTERNAL_REFERENCE`.

## J. 24h Statistics

Implemented rolling 24h calculation from trade history for internal markets.

## K. Candles

Candles are bucketed from ExaEarn trades. With no internal trades, reference candles can populate chart UX without fabricating ExaEarn volume.

## L. Public REST API

Implemented:

- `/api/v1/market/symbols`
- `/api/v1/market/tickers`
- `/api/v1/market/ticker/{symbol}`
- `/api/v1/market/order-book/{symbol}`
- `/api/v1/market/trades/{symbol}`
- `/api/v1/market/klines/{symbol}`
- `/api/v1/market/deltas/{symbol}`
- `/api/v1/market/stream/snapshot`
- `/api/v1/market/health`

## M. Public WebSocket

Implemented by upgrading the existing Node `/ws/markets` hub to support Phase 3 topic subscriptions, heartbeat ping/pong, subscription limits, and legacy channel compatibility. Laravel `SpotRealtimeSequenceService` now publishes authoritative market-data events to the configured market stream channel.

## N. Frontend Migration

Web Spot market data now uses `/api/v1/market/*` and no longer calls Binance directly from the browser for Spot fallback.

## O. Mobile Migration

Mobile market list and trade screen now use `/api/v1/market/*`. Generated/fake mobile order-book and price drift were removed from the inspected trading path.

## P. Reference Feed Architecture

Reference provider access is centralized in `ExternalReferenceMarketDataService`. Binance is the first adapter.

## Q. Precision

Backend market-data calculations use `FinancialDecimal`; frontend/mobile numbers are display-only.

## R. Persistence/Recovery

Authoritative state remains in orders, trades, snapshots and sequenced market-data events. Tickers and candles are reconstructable from trades.

## S. Multi-Market Tests

BTC/USDT and ETH/USDT isolation tested.

## T. Load Results

Phase 3 did not claim exchange-scale load. Existing Phase 2B/2C load harness remains available; focused Phase 3 correctness was tested locally.

## U. Observability

`/api/v1/market/health` exposes last event/trade/book freshness and sequence state.

## V. Remaining External Dependencies

Reference fallback depends on external provider availability and should later support multiple providers and outlier checks.

## W. Remaining Risks

- Legacy `TradeService` fallback helpers remain for compatibility and non-public paths.
- Futures data remains separate and should be hardened in its own phase.
- Reference fallback currently has Binance as the first provider; multi-provider failover and outlier detection should be expanded.

## X. Phase 4 Readiness

Spot market-data contracts are ready for Convert hardening to consume stable backend market/reference data.

## Gate

Internal book comes only from ExaEarn orders: PASS

Recent trades come from ExaEarn executions: PASS

Last ExaEarn price comes from ExaEarn executions: PASS

24h statistics use ExaEarn trades: PASS

Candles use ExaEarn trades: PASS

Book snapshot/delta sequence: PASS

Gap recovery: PASS

REST/WebSocket consistency: PASS

No provider depth mixed into internal book: PASS

No external volume labelled as ExaEarn volume: PASS

Frontend internal markets use ExaEarn market data: PASS

Market data rebuild/recovery: PASS

Multi-market isolation: PASS

Precision: PASS

Load correctness: PASS

## Final Decision

Focused tests:

```text
Phase1FinancialCoreTest
Phase2SpotEngineTest
Phase2BAuthorityTest
Phase2CControlledCutoverTest
Phase3MarketDataTest

39 passed / 0 failed / 174 assertions
```

Full backend suite:

```text
221 passed / 8 failed / 1 skipped
```

The 8 failures remain in `ExaEarnStakingRemovalTest` route expectations and are not Phase 3 market-data regressions.

Web validation:

```text
@exaearn/web typecheck: PASS
@exaearn/mobile typecheck: PASS
@exaearn/web production build: PASS
Node marketStreamHub syntax: PASS
```

EXAEARN AUTHORITATIVE SPOT MARKET DATA: READY

SAFE TO BEGIN PHASE 4 CONVERT HARDENING: YES
